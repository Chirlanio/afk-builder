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

    private function rate(Hero $hero, string $tier, string $mode = 'pvp', string $stage = 'end'): void
    {
        HeroRating::create(['hero_id' => $hero->id, 'mode' => $mode, 'stage' => $stage, 'tier' => $tier, 'source' => 'afk.guide']);
    }

    public function test_target_planning_lists_only_catalog_heroes_rated_a_or_better_in_the_context(): void
    {
        $user = User::factory()->create();
        foreach (['S+', 'S', 'A+', 'A'] as $i => $tier) {
            $this->rate($this->makeHero("Strong{$i}"), $tier);
        }
        $this->rate($this->makeHero('Weak'), 'B+');
        $this->rate($this->makeHero('PveOnly'), 'S+', 'pve');
        $owned = $this->makeHero('Owned');
        $this->rate($owned, 'C');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $owned->id, 'level' => 240, 'signature_item' => 30, 'furniture' => 9, 'engraving' => 60,
        ]);
        $ownedStar = $this->makeHero('OwnedStar');
        $this->rate($ownedStar, 'S');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $ownedStar->id, 'level' => 180, 'signature_item' => 10, 'furniture' => 3, 'engraving' => 30, 'hp' => 900_000,
        ]);

        $markdown = $this->export($user, ['planning' => 'target'])->assertOk()->json('markdown');

        foreach (range(0, 3) as $i) {
            $this->assertStringContainsString("**Strong{$i}**", $markdown);
        }
        $this->assertStringNotContainsString('**Weak**', $markdown);
        $this->assertStringNotContainsString('**PveOnly**', $markdown);
        $this->assertStringNotContainsString('**Owned**', $markdown);
        $this->assertStringNotContainsString('SI +30', $markdown);
        $ownedStarLine = $this->lineOf('OwnedStar', $markdown);
        foreach (['nível 180', 'SI +10', 'F3', 'E30', 'Atributos de tela'] as $investment) {
            $this->assertStringNotContainsString($investment, $ownedStarLine);
        }
        $this->assertStringContainsString('Investimento máximo', $markdown);
        $this->assertStringContainsString('qualquer herói do jogo', $markdown);
    }

    public function test_target_planning_reads_the_end_game_rating_whatever_the_roster_stage(): void
    {
        $user = User::factory()->create();
        $earlyStar = $this->makeHero('EarlyStar');
        $this->rate($earlyStar, 'S+', 'pvp', 'early');
        $this->rate($earlyStar, 'B', 'pvp', 'end');
        $lateStar = $this->makeHero('LateStar');
        $this->rate($lateStar, 'C', 'pvp', 'early');
        $this->rate($lateStar, 'S', 'pvp', 'end');
        // Roster de início de jogo.
        $user->defaultGameAccount->userHeroes()->create(['hero_id' => $this->makeHero('Rookie')->id, 'level' => 40, 'ascension_value' => 1]);

        $markdown = $this->export($user, ['planning' => 'target'])->assertOk()->json('markdown');

        $this->assertStringNotContainsString('**EarlyStar**', $markdown);
        $this->assertStringContainsString('tier S', $this->lineOf('LateStar', $markdown));
        $this->assertStringContainsString('fim de jogo', $markdown);
    }

    public function test_empty_roster_does_not_block_target_planning(): void
    {
        $user = User::factory()->create();
        $this->rate($this->makeHero('Talene'), 'S+');

        $response = $this->export($user, ['planning' => 'target'])->assertOk();

        $this->assertNull($response->json('warning'));
        $this->assertStringContainsString('**Talene**', $response->json('markdown'));
    }

    private function exportObjective(User $user, array $params): TestResponse
    {
        return $this->actingAs($user)->getJson('/roster/ai-export?'.http_build_query($params + ['planning' => 'target']));
    }

    public function test_campaign_requires_a_stage_and_asks_for_the_chosen_number_of_teams(): void
    {
        $user = User::factory()->create();
        $this->rate($this->makeHero('Talene'), 'S+', 'pve');

        $this->exportObjective($user, ['objective' => 'campaign'])->assertJsonValidationErrors('stage');

        $markdown = $this->exportObjective($user, ['objective' => 'campaign', 'stage' => '45-60', 'teams' => 3])
            ->assertOk()
            ->json('markdown');

        $this->assertStringContainsString('45-60', $markdown);
        $this->assertStringContainsString('Monte 3 Equipes', $markdown);
        $this->assertStringContainsString('Equipe 3:', $markdown);
        $this->assertStringNotContainsString('Equipe 4:', $markdown);
    }

    public function test_dragon_trial_asks_two_or_three_teams_by_stage_with_its_own_context_and_allowed_heroes(): void
    {
        $user = User::factory()->create();
        $draconis = $this->makeHero('Talene');
        $this->rate($draconis, 'S+', 'dragonforge');
        $this->rate($draconis, 'D', 'pve');

        $this->exportObjective($user, ['objective' => 'dragon_trial'])->assertJsonValidationErrors('stage');
        $this->exportObjective($user, ['objective' => 'dragon_trial', 'stage' => 13])->assertJsonValidationErrors('stage');

        $stage4 = $this->exportObjective($user, ['objective' => 'dragon_trial', 'stage' => 4])->assertOk()->json('markdown');
        $stage8 = $this->exportObjective($user, ['objective' => 'dragon_trial', 'stage' => 8])->assertOk()->json('markdown');

        $this->assertStringContainsString('Monte 2 Equipes', $stage4);
        $this->assertStringContainsString('Monte 3 Equipes', $stage8);
        $this->assertStringContainsString('tier S+', $this->lineOf('Talene', $stage8));
        $this->assertStringContainsString('Draconis', $stage8);
        $this->assertStringContainsString('Dimensionals', $stage8);
        $this->assertStringContainsString('Cristal', $stage8);
    }

    public function test_multi_team_objectives_explain_exclusive_teams_and_single_character(): void
    {
        $user = User::factory()->create();
        $this->rate($this->makeHero('Talene'), 'S+', 'pve');
        $this->rate($this->makeHero('DraconisHero'), 'S+', 'dragonforge');

        foreach ([
            ['objective' => 'campaign', 'stage' => '45-60', 'teams' => 2],
            ['objective' => 'dragon_trial', 'stage' => 5],
        ] as $params) {
            $markdown = $this->exportObjective($user, $params)->assertOk()->json('markdown');

            $this->assertStringContainsString('Nenhum herói pode estar em duas Equipes', $markdown);
            $this->assertStringContainsString('Desperta', $markdown);
        }
    }

    public function test_single_character_rule_holds_even_with_a_single_team(): void
    {
        $user = User::factory()->create();
        $this->rate($this->makeHero('Talene'), 'S+', 'pve');

        $markdown = $this->exportObjective($user, ['objective' => 'campaign', 'stage' => '45-60', 'teams' => 1])
            ->assertOk()
            ->json('markdown');

        $this->assertStringContainsString('Desperta', $markdown);
        $this->assertStringNotContainsString('Nenhum herói pode estar em duas Equipes', $markdown);
    }

    public function test_kings_tower_requires_a_tower_and_faction_towers_restrict_factions(): void
    {
        $user = User::factory()->create();
        $this->rate($this->makeHero('Talene'), 'S+', 'pve');

        $this->exportObjective($user, ['objective' => 'kings_tower'])->assertJsonValidationErrors('tower');

        $main = $this->exportObjective($user, ['objective' => 'kings_tower', 'tower' => 'kings_tower'])->assertOk()->json('markdown');
        $light = $this->exportObjective($user, ['objective' => 'kings_tower', 'tower' => 'tower_of_light'])->assertOk()->json('markdown');

        $this->assertStringContainsString('Torre do Rei', $main);
        $this->assertStringNotContainsString('Só heróis', $main);
        $this->assertStringContainsString('Tower of Light', $light);
        $this->assertStringContainsString('Só heróis Lightbearers', $light);
    }

    public function test_dream_realm_boss_uses_its_context_and_says_control_does_not_work(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('Talene');
        $this->rate($hero, 'S+', 'twisted_realm');
        $this->rate($hero, 'D', 'pve');

        $markdown = $this->exportObjective($user, ['objective' => 'dream_realm'])->assertOk()->json('markdown');

        $this->assertStringContainsString('tier S+', $this->lineOf('Talene', $markdown));
        $this->assertStringContainsString('Reino dos Sonhos', $markdown);
        $this->assertStringContainsString('controle não tem efeito', $markdown);
        $this->assertStringContainsString('Monte 1 Equipe ', $markdown);
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
