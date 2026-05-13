<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationSuggestWeightOverridesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            ChallengeTypeSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeHero(string $slug, string $factionCode = 'lightbearer', string $classCode = 'tank'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $factionCode)->value('id'),
            'hero_class_id' => HeroClass::where('code', $classCode)->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_suggest_team_aceita_weight_overrides(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 5; $i++) {
            $hero = $this->makeHero("h{$i}");
            $user->userHeroes()->create([
                'hero_id' => $hero->id,
                'level' => 240,
                'ascension_value' => 12,
                'ascension_code' => 'ascended_1_star',
            ]);
        }

        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/1/suggest", [
                'teams' => [['position' => 1, 'heroes' => []]],
                'weight_overrides' => [
                    'power_weight' => 2.5,
                    'synergy_weight' => 0.3,
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['additions', 'score']);
    }

    public function test_suggest_team_rejeita_peso_acima_de_3(): void
    {
        $user = User::factory()->create();
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/1/suggest", [
                'teams' => [['position' => 1, 'heroes' => []]],
                'weight_overrides' => ['power_weight' => 5.0],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weight_overrides.power_weight']);
    }

    public function test_suggest_team_rejeita_peso_negativo(): void
    {
        $user = User::factory()->create();
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/1/suggest", [
                'teams' => [['position' => 1, 'heroes' => []]],
                'weight_overrides' => ['power_weight' => -1.0],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weight_overrides.power_weight']);
    }

    public function test_suggest_all_aceita_weight_overrides(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            $hero = $this->makeHero("h{$i}");
            $user->userHeroes()->create([
                'hero_id' => $hero->id,
                'level' => 240,
                'ascension_value' => 12,
                'ascension_code' => 'ascended_1_star',
            ]);
        }

        $challengeType = ChallengeType::where('code', 'abyssal_expedition')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 3,
        ]);
        for ($pos = 1; $pos <= 3; $pos++) {
            $formation->teams()->create(['position' => $pos]);
        }

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/suggest-all", [
                'teams' => [
                    ['position' => 1, 'heroes' => []],
                    ['position' => 2, 'heroes' => []],
                    ['position' => 3, 'heroes' => []],
                ],
                'weight_overrides' => ['synergy_weight' => 2.0],
            ])
            ->assertOk();
    }

    public function test_omitir_weight_overrides_usa_defaults(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('h1');
        $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
        ]);
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/1/suggest", [
                'teams' => [['position' => 1, 'heroes' => []]],
            ])
            ->assertOk();
    }
}
