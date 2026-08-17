<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    private function makeFormationWithTeams(User $user, string $name, int $teamsCount = 1): Formation
    {
        $challengeType = ChallengeType::where('code', $teamsCount > 1 ? 'legends_championship' : 'campaign')->first();

        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => $name,
            'mode' => 'real',
            'teams_count' => $teamsCount,
        ]);

        for ($pos = 1; $pos <= $teamsCount; $pos++) {
            $formation->teams()->create(['position' => $pos]);
        }

        return $formation;
    }

    private function makeHero(string $slug, string $faction, string $class): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $faction)->value('id'),
            'hero_class_id' => HeroClass::where('code', $class)->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_owner_compara_suas_formacoes(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFormationWithTeams($user, 'A');
        $b = $this->makeFormationWithTeams($user, 'B');

        $this->actingAs($user)
            ->get("/formations/compare/{$a->id}/{$b->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('formations/compare')
                ->where('formationA.id', $a->id)
                ->where('formationB.id', $b->id)
                ->has('formationA.total_score')
                ->has('formationB.total_score')
            );
    }

    public function test_nao_pode_comparar_formacoes_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $a = $this->makeFormationWithTeams($owner, 'A');
        $b = $this->makeFormationWithTeams($other, 'B');

        $this->actingAs($owner)
            ->get("/formations/compare/{$a->id}/{$b->id}")
            ->assertForbidden();
    }

    public function test_compare_inclui_scores_por_equipe(): void
    {
        $user = User::factory()->create();
        $a = $this->makeFormationWithTeams($user, 'A');
        $b = $this->makeFormationWithTeams($user, 'B');

        // Adiciona 5 lightbearers em A (deve dar 5-stack)
        $h1 = $this->makeHero('h1', 'lightbearer', 'tank');
        $h2 = $this->makeHero('h2', 'lightbearer', 'warrior');
        $h3 = $this->makeHero('h3', 'lightbearer', 'ranger');
        $h4 = $this->makeHero('h4', 'lightbearer', 'mage');
        $h5 = $this->makeHero('h5', 'lightbearer', 'support');

        $teamA = $a->teams->first();
        foreach ([$h1, $h2, $h3, $h4, $h5] as $i => $hero) {
            $teamA->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => $i + 1]);
        }

        $response = $this->actingAs($user)->get("/formations/compare/{$a->id}/{$b->id}");
        $response->assertOk();

        $page = $response->viewData('page');
        $props = $page['props'];

        $this->assertEqualsWithDelta(1.0, $props['formationA']['teams'][0]['score']['faction_bonus'], 0.001);
        $this->assertEqualsWithDelta(0.0, $props['formationB']['teams'][0]['score']['faction_bonus'], 0.001);
    }
}
