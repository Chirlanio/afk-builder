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

class FormationEditScoreBreakdownTest extends TestCase
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

    private function makeHero(string $slug): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_edit_inclui_scores_by_team_position_no_payload(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
        ]);

        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 1,
        ]);
        $team = $formation->teams()->create(['position' => 1]);
        $team->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => 1]);

        $this->actingAs($user)
            ->get("/formations/{$formation->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('formations/edit')
                ->has('scoresByTeamPosition')
                ->has('scoresByTeamPosition.1', fn ($s) => $s
                    ->where('faction_bonus', fn ($v) => is_numeric($v))
                    ->where('class_balance', fn ($v) => is_numeric($v))
                    ->where('synergy', fn ($v) => is_numeric($v))
                    ->where('power', fn ($v) => is_numeric($v))
                    ->where('tier_rating', fn ($v) => is_numeric($v))
                    ->where('feature_bonus', fn ($v) => is_numeric($v))
                    ->where('furniture_effects', fn ($v) => is_numeric($v))
                    ->where('engraving_bonus', fn ($v) => is_numeric($v))
                    ->where('total', fn ($v) => is_numeric($v))
                    ->etc()
                )
            );
    }

    public function test_team_vazio_retorna_null_no_score(): void
    {
        $user = User::factory()->create();
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'target',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $this->actingAs($user)
            ->get("/formations/{$formation->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('formations/edit')
                ->where('scoresByTeamPosition.1', null)
            );
    }

    public function test_edit_inclui_scoring_profile_defaults(): void
    {
        $user = User::factory()->create();
        $challengeType = ChallengeType::where('code', 'arena_of_heroes')->first();
        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'PvP test',
            'mode' => 'target',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $response = $this->actingAs($user)
            ->get("/formations/{$formation->id}/edit")
            ->assertOk();

        $defaults = $response->viewData('page')['props']['scoringProfileDefaults'];
        $this->assertSame(1.2, (float) $defaults['faction_bonus_weight']);
        $this->assertSame(0.9, (float) $defaults['class_balance_weight']);
        $this->assertSame(1.5, (float) $defaults['synergy_weight']);
        $this->assertSame(1.0, (float) $defaults['power_weight']);
        $this->assertSame(1.0, (float) $defaults['tier_rating_weight']);
        $this->assertSame(0.5, (float) $defaults['feature_weight']);
        $this->assertSame(0.0, (float) $defaults['furniture_effect_weight']);
    }
}
