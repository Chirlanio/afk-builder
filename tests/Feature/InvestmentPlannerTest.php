<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Services\Optimization\InvestmentPlanner;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_vazio_retorna_array_vazio(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $planner = app(InvestmentPlanner::class);
        $suggestions = $planner->plan($user);

        $this->assertSame([], $suggestions);
    }

    public function test_sugere_upgrade_para_hero_com_si_baixo(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $hero = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 10, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);
        $suggestions = $planner->plan($user);

        $this->assertNotEmpty($suggestions);
        // Pelo menos 1 sugestão de SI 10→30 deve aparecer
        $siSug = collect($suggestions)->firstWhere('kind', 'signature_item');
        $this->assertNotNull($siSug);
        $this->assertSame(10, $siSug['from']);
        $this->assertSame(30, $siSug['to']);
        $this->assertGreaterThan(0, $siSug['power_gain']);
    }

    public function test_hero_no_max_de_si_nao_recebe_sugestao_de_si(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // lightbearer cap = 30. Hero já em SI 30 → sem sugestão de SI
        $hero = $this->makeHero('maxed', 'lightbearer', 'tank', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 30, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);
        $suggestions = $planner->plan($user);

        $siSugs = collect($suggestions)->where('kind', 'signature_item');
        $this->assertCount(0, $siSugs);
    }

    public function test_celestial_pode_subir_para_si_40(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // celestial cap = 40. Hero SI 30 → sugere subir pra 40
        $hero = $this->makeHero('celest', 'celestial', 'mage', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars',
            'signature_item' => 30, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);
        $suggestions = $planner->plan($user);

        $siSug = collect($suggestions)->firstWhere('kind', 'signature_item');
        $this->assertNotNull($siSug);
        $this->assertSame(30, $siSug['from']);
        $this->assertSame(40, $siSug['to']);
    }

    public function test_formation_scope_aplica_multiplier_in_formation(): void
    {
        $this->seedCatalog();
        $this->seed(ChallengeTypeSeeder::class);
        $user = User::factory()->create();

        // 2 heroes identicos (mesmo upgrade), só um na formation
        $h1 = $this->makeHero('in-team', 'mauler', 'warrior', 'ascended');
        $h2 = $this->makeHero('not-in-team', 'mauler', 'warrior', 'ascended');

        foreach ([$h1, $h2] as $h) {
            $user->userHeroes()->create([
                'hero_id' => $h->id,
                'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
                'signature_item' => 10, 'furniture' => 0, 'engraving' => 0,
            ]);
        }

        // Cria formation com h1 dentro
        $challengeType = \App\Models\ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 1,
            'challenge_type_id' => $challengeType->id,
        ]);
        $team = $formation->teams()->create(['position' => 1, 'name' => 'Team']);
        $team->teamHeroes()->create(['hero_id' => $h1->id, 'slot' => 1, 'row' => 'front']);

        $planner = app(InvestmentPlanner::class);

        // Sem formation: ambos têm score parecido
        $rosterScope = collect($planner->plan($user))->keyBy('hero_id');
        $this->assertEqualsWithDelta(
            $rosterScope[$h1->id]['score'],
            $rosterScope[$h2->id]['score'],
            0.001,
        );

        // Com formation: h1 ganha multiplier
        $formationScope = collect($planner->plan($user, $formation))->keyBy('hero_id');
        $this->assertGreaterThan(
            $formationScope[$h2->id]['score'],
            $formationScope[$h1->id]['score'],
        );
        $this->assertContains('in_formation', $formationScope[$h1->id]['reasons']);
        $this->assertNotContains('in_formation', $formationScope[$h2->id]['reasons']);
    }

    public function test_awakened_hero_recebe_multiplier(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // slug != Str::slug(name) → awakened
        $awakened = Hero::create([
            'slug' => 'lucius-awakened',
            'name' => 'Lucius',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true, 'pending_review' => false,
        ]);

        $user->userHeroes()->create([
            'hero_id' => $awakened->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 10, 'furniture' => 0, 'engraving' => 0,
        ]);

        $suggestions = app(InvestmentPlanner::class)->plan($user);

        $this->assertNotEmpty($suggestions);
        $this->assertContains('awakened', $suggestions[0]['reasons']);
    }

    public function test_pagina_planner_retorna_payload_correto(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $hero = $this->makeHero('beta', 'wilder', 'support', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $response = $this->actingAs($user)->get('/roster/planner')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertNotEmpty($props['suggestions']);
        $this->assertSame(1, $props['rosterSize']);
        $this->assertNull($props['selectedFormationId']);
    }

    public function test_pagina_planner_guest_redireciona(): void
    {
        $this->get('/roster/planner')->assertRedirect('/login');
    }

    private function seedCatalog(): void
    {
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeHero(string $slug, string $factionCode, string $classCode, string $tierCode): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $factionCode)->value('id'),
            'hero_class_id' => HeroClass::where('code', $classCode)->value('id'),
            'tier_id' => Tier::where('code', $tierCode)->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }
}
