<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\Equipment;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroRating;
use App\Models\Tier;
use App\Models\User;
use App\Services\ActiveGameAccount;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AiExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    private function makeHero(string $name, string $faction = 'lightbearer', string $class = 'mage'): Hero
    {
        return Hero::create([
            'slug' => strtolower($name),
            'name' => $name,
            'faction_id' => Faction::where('code', $faction)->value('id'),
            'hero_class_id' => HeroClass::where('code', $class)->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    private function export(User $user, array $params = []): TestResponse
    {
        return $this->actingAs($user)->getJson('/roster/ai-export?'.http_build_query($params + [
            'objective' => 'arena_of_heroes',
            'planning' => 'real',
            'side' => 'defense',
        ]));
    }

    public function test_real_planning_lists_roster_heroes_with_their_investment(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('Lucius', 'lightbearer', 'tank');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id, 'level' => 240, 'ascension_code' => 'ascended_5_stars', 'ascension_value' => 16,
            'signature_item' => 30, 'furniture' => 9, 'engraving' => 60,
        ]);

        $markdown = $this->export($user)->assertOk()->json('markdown');

        $this->assertStringContainsString('Lucius', $markdown);
        $this->assertStringContainsString('Lightbearer', $markdown);
        $this->assertStringContainsString('Tank', $markdown);
        $this->assertStringContainsString('Ascended 5★', $markdown);
        $this->assertStringContainsString('SI +30', $markdown);
        $this->assertStringContainsString('F9', $markdown);
        $this->assertStringContainsString('E60', $markdown);
        $this->assertStringContainsString('nível 240', $markdown);
    }

    public function test_investment_includes_equipment_and_artifact(): void
    {
        $this->seed(EquipmentSeeder::class);
        $user = User::factory()->create();
        $artifact = Artifact::create([
            'slug' => 'duras-grace', 'name' => "Dura's Grace", 'kind' => Artifact::KIND_DURA,
            'attributes' => [], 'powers' => [], 'max_stars' => 5, 'is_active' => true,
        ]);
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $this->makeHero('Lucius')->id, 'level' => 240,
            'boots_equipment_id' => Equipment::where('slug', 'gaius-grevas-lightbearer')->value('id'), 'boots_equipment_level' => 3,
            'artifact_id' => $artifact->id, 'artifact_star_level' => 4,
        ]);

        $line = $this->lineOf('Lucius', $this->export($user)->assertOk()->json('markdown'));

        $this->assertStringContainsString('Gaius Greaves +3', $line);
        $this->assertStringContainsString("Dura's Grace 4★", $line);
    }

    public function test_game_screen_stats_appear_only_when_registered(): void
    {
        $user = User::factory()->create();
        $account = $user->defaultGameAccount;
        $account->userHeroes()->create([
            'hero_id' => $this->makeHero('Thoran')->id, 'level' => 200,
            'hp' => 1_500_000_000, 'attack' => 42_000_000, 'crit_rate' => 12.5,
        ]);
        $account->userHeroes()->create(['hero_id' => $this->makeHero('Brutus')->id, 'level' => 200]);

        $markdown = $this->export($user)->assertOk()->json('markdown');

        $thoran = $this->lineOf('Thoran', $markdown);
        $this->assertStringContainsString('HP 1500000000', $thoran);
        $this->assertStringContainsString('ATK 42000000', $thoran);
        $this->assertStringContainsString('Crit Rate 12.5', $thoran);
        $this->assertStringNotContainsString('HP', $this->lineOf('Brutus', $markdown));
    }

    public function test_tier_rating_shown_is_the_pvp_context_one_for_the_arena(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('Talene');
        foreach (['early', 'mid', 'end'] as $stage) {
            HeroRating::create(['hero_id' => $hero->id, 'mode' => 'pvp', 'stage' => $stage, 'tier' => 'S+', 'source' => 'afk.guide']);
            HeroRating::create(['hero_id' => $hero->id, 'mode' => 'pve', 'stage' => $stage, 'tier' => 'D', 'source' => 'afk.guide']);
        }
        $user->defaultGameAccount->userHeroes()->create(['hero_id' => $hero->id, 'level' => 240]);

        $line = $this->lineOf('Talene', $this->export($user)->assertOk()->json('markdown'));

        $this->assertStringContainsString('tier S+', $line);
        $this->assertStringNotContainsString('tier D', $line);
    }

    public function test_ratings_from_other_contexts_are_not_presented_as_the_arena_tier(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('Ainz');
        HeroRating::create(['hero_id' => $hero->id, 'mode' => 'overall', 'stage' => 'end', 'tier' => 'S+', 'source' => 'afk.guide']);
        HeroRating::create(['hero_id' => $hero->id, 'mode' => 'overall', 'stage' => 'all', 'tier' => 'S', 'source' => 'allclash']);
        $user->defaultGameAccount->userHeroes()->create(['hero_id' => $hero->id, 'level' => 240]);

        $line = $this->lineOf('Ainz', $this->export($user)->assertOk()->json('markdown'));

        $this->assertStringNotContainsString('tier S', $line);
        $this->assertStringContainsString('sem tier', $line);
    }

    public function test_heroes_carry_their_capabilities(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('Rowan');
        $hero->update(['capabilities' => ['energy_gain' => 2, 'buff_ally' => 1]]);
        $user->defaultGameAccount->userHeroes()->create(['hero_id' => $hero->id, 'level' => 240]);

        $line = $this->lineOf('Rowan', $this->export($user)->assertOk()->json('markdown'));

        $this->assertStringContainsString('energy_gain 2', $line);
        $this->assertStringContainsString('buff_ally 1', $line);
    }

    public function test_text_carries_the_arena_prompt_and_the_fixed_answer_format(): void
    {
        $user = User::factory()->create();
        $user->defaultGameAccount->userHeroes()->create(['hero_id' => $this->makeHero('Lucius')->id, 'level' => 240]);

        $markdown = $this->export($user, ['side' => 'attack'])->assertOk()->json('markdown');

        $this->assertStringContainsString('Arena dos Heróis', $markdown);
        $this->assertStringContainsString('ataque', $markdown);
        $this->assertStringContainsString('Responda em português', $markdown);
        $this->assertStringContainsString('AFK-BUILDER-RESPOSTA v1', $markdown);
        $this->assertStringContainsString('Equipe 1:', $markdown);
    }

    public function test_empty_roster_warns_and_points_to_target_planning(): void
    {
        $user = User::factory()->create();

        $response = $this->export($user)->assertOk();

        $this->assertStringContainsString('Roster', $response->json('warning'));
        $this->assertStringContainsString('Planejamento alvo', $response->json('warning'));
        $this->assertSame('', $response->json('markdown'), 'sem heróis não há texto para exportar');
    }

    public function test_names_stay_in_english_for_a_portuguese_speaking_user(): void
    {
        $user = User::factory()->create(['locale' => 'pt']);
        $user->defaultGameAccount->userHeroes()->create(['hero_id' => $this->makeHero('Lucius', 'lightbearer', 'tank')->id, 'level' => 240]);

        $line = $this->lineOf('Lucius', $this->export($user)->assertOk()->json('markdown'));

        $this->assertStringContainsString('Lightbearers', $line);
        $this->assertStringContainsString('Tank', $line);
        $this->assertStringNotContainsString('Portadores', $line);
        $this->assertStringNotContainsString('Tanque', $line);
    }

    public function test_internal_numbers_never_reach_the_text(): void
    {
        $user = User::factory()->create();
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $this->makeHero('Lucius')->id, 'level' => 240, 'power_rating' => 987654,
        ]);

        $markdown = $this->export($user)->assertOk()->json('markdown');

        $this->assertStringNotContainsString('987654', $markdown);
        $this->assertStringNotContainsStringIgnoringCase('pontuação', $markdown);
        $this->assertStringNotContainsStringIgnoringCase('poder', $markdown);
    }

    public function test_another_users_game_account_is_never_exported(): void
    {
        $owner = User::factory()->create();
        $owner->defaultGameAccount->userHeroes()->create(['hero_id' => $this->makeHero('Ezizh')->id, 'level' => 240]);
        $intruder = User::factory()->create();

        $markdown = $this->actingAs($intruder)
            ->withSession([ActiveGameAccount::SESSION_KEY => $owner->defaultGameAccount->id])
            ->getJson('/roster/ai-export?objective=arena_of_heroes&planning=real&side=defense')
            ->assertOk()
            ->json('markdown');

        $this->assertStringNotContainsString('Ezizh', $markdown);
    }

    public function test_invalid_objective_or_missing_parameter_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->export($user, ['objective' => 'world_domination'])->assertJsonValidationErrors('objective');
        $this->actingAs($user)
            ->getJson('/roster/ai-export?objective=arena_of_heroes&planning=real')
            ->assertJsonValidationErrors('side');
    }

    public function test_guests_cannot_export(): void
    {
        $this->getJson('/roster/ai-export?objective=arena_of_heroes&planning=real&side=defense')->assertUnauthorized();
    }

    private function lineOf(string $heroName, string $markdown): string
    {
        foreach (explode("\n", $markdown) as $line) {
            if (str_contains($line, "**{$heroName}**")) {
                return $line;
            }
        }
        $this->fail("{$heroName} não aparece no texto");
    }
}
