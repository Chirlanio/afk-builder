<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserResource;
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
        $suggestions = $planner->plan($user->defaultGameAccount);

        $this->assertSame([], $suggestions);
    }

    public function test_sugere_upgrade_para_hero_com_si_baixo(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $hero = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 10, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);
        $suggestions = $planner->plan($user->defaultGameAccount);

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
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 30, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);
        $suggestions = $planner->plan($user->defaultGameAccount);

        $siSugs = collect($suggestions)->where('kind', 'signature_item');
        $this->assertCount(0, $siSugs);
    }

    public function test_celestial_pode_subir_para_si_40(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // celestial cap = 40. Hero SI 30 → sugere subir pra 40
        $hero = $this->makeHero('celest', 'celestial', 'mage', 'ascended');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars',
            'signature_item' => 30, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);
        $suggestions = $planner->plan($user->defaultGameAccount);

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
            $user->defaultGameAccount->userHeroes()->create([
                'hero_id' => $h->id,
                'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
                'signature_item' => 10, 'furniture' => 0, 'engraving' => 0,
            ]);
        }

        // Cria formation com h1 dentro
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->defaultGameAccount->formations()->create([
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => 1,
            'challenge_type_id' => $challengeType->id,
        ]);
        $team = $formation->teams()->create(['position' => 1, 'name' => 'Team']);
        $team->teamHeroes()->create(['hero_id' => $h1->id, 'slot' => 1, 'row' => 'front']);

        $planner = app(InvestmentPlanner::class);

        // Sem formation: ambos têm score parecido
        $rosterScope = collect($planner->plan($user->defaultGameAccount))->keyBy('hero_id');
        $this->assertEqualsWithDelta(
            $rosterScope[$h1->id]['score'],
            $rosterScope[$h2->id]['score'],
            0.001,
        );

        // Com formation: h1 ganha multiplier
        $formationScope = collect($planner->plan($user->defaultGameAccount, $formation))->keyBy('hero_id');
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

        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $awakened->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 10, 'furniture' => 0, 'engraving' => 0,
        ]);

        $suggestions = app(InvestmentPlanner::class)->plan($user->defaultGameAccount);

        $this->assertNotEmpty($suggestions);
        $this->assertContains('awakened', $suggestions[0]['reasons']);
    }

    public function test_pagina_planner_retorna_payload_correto(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $hero = $this->makeHero('beta', 'wilder', 'support', 'ascended');
        $user->defaultGameAccount->userHeroes()->create([
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

    public function test_resource_cost_emblemas_si_diferentes_buckets(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();
        $hero = $this->makeHero('h', 'lightbearer', 'tank', 'ascended');
        $uh = $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);

        // SI 0→30: usa primordial (levels 1-20) + amplifying (21-30)
        $cost = $planner->resourceCostFor($uh, ['kind' => 'signature_item', 'from' => 0, 'to' => 30]);
        $this->assertArrayHasKey('primordial_emblems', $cost);
        $this->assertArrayHasKey('amplifying_emblems', $cost);
        $this->assertGreaterThan(0, $cost['primordial_emblems']);
        $this->assertGreaterThan(0, $cost['amplifying_emblems']);
        $this->assertArrayNotHasKey('faction_emblems', $cost);

        // SI 30→40 (Celestial only): puro faction
        $costFaction = $planner->resourceCostFor($uh, ['kind' => 'signature_item', 'from' => 30, 'to' => 40]);
        $this->assertArrayHasKey('faction_emblems', $costFaction);
        $this->assertArrayNotHasKey('primordial_emblems', $costFaction);
    }

    public function test_resource_cost_furniture_e_engraving_pool(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();
        $hero = $this->makeHero('h', 'lightbearer', 'tank', 'ascended');
        $uh = $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $planner = app(InvestmentPlanner::class);

        // F0→F3 = 3 unidades
        $f = $planner->resourceCostFor($uh, ['kind' => 'furniture', 'from' => 0, 'to' => 3]);
        $this->assertSame(['mythic_furniture' => 3], $f);

        // E30→E60 = 30 unidades
        $e = $planner->resourceCostFor($uh, ['kind' => 'engraving', 'from' => 30, 'to' => 60]);
        $this->assertSame(['engraving_scrolls' => 30], $e);

        // Ascension: vazio (sem pool global)
        $a = $planner->resourceCostFor($uh, ['kind' => 'ascension', 'from' => 5, 'to' => 10]);
        $this->assertSame([], $a);
    }

    public function test_affordable_flag_quando_recursos_passados(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();
        $hero = $this->makeHero('rich', 'lightbearer', 'tank', 'ascended');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $resources = UserResource::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'primordial_emblems' => 9_999, // suficiente pra qualquer SI bucket
            'amplifying_emblems' => 9_999,
            'faction_emblems' => 0,
            'mythic_furniture' => 0,
            'engraving_scrolls' => 0,
        ]);

        $suggestions = app(InvestmentPlanner::class)->plan($user->defaultGameAccount, null, 10, $resources);

        $siSug = collect($suggestions)->firstWhere('kind', 'signature_item');
        $this->assertNotNull($siSug);
        $this->assertTrue($siSug['affordable']);

        // Furniture e Engraving devem ser unaffordable (recursos = 0)
        $fSug = collect($suggestions)->firstWhere('kind', 'furniture');
        if ($fSug !== null) {
            $this->assertFalse($fSug['affordable']);
        }

        $eSug = collect($suggestions)->firstWhere('kind', 'engraving');
        if ($eSug !== null) {
            $this->assertFalse($eSug['affordable']);
        }
    }

    public function test_affordable_null_quando_recursos_ausentes(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();
        $hero = $this->makeHero('h', 'lightbearer', 'tank', 'ascended');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $suggestions = app(InvestmentPlanner::class)->plan($user->defaultGameAccount); // sem $resources

        $this->assertNotEmpty($suggestions);
        $this->assertNull($suggestions[0]['affordable']);
        $this->assertNull($suggestions[0]['resource_cost']);
    }

    public function test_pentagram_hero_recebe_multiplier_in_pentagram(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // 6 heros idênticos exceto pelo nível — top 5 vai pro pentagrama
        $levels = [240, 220, 200, 180, 160, 100];
        $heroes = [];
        foreach ($levels as $i => $level) {
            $h = $this->makeHero("h{$i}", 'lightbearer', 'tank', 'ascended');
            $user->defaultGameAccount->userHeroes()->create([
                'hero_id' => $h->id, 'level' => $level, 'ascension_value' => 12,
                'ascension_code' => 'ascended_1_star', 'signature_item' => 10, 'furniture' => 0, 'engraving' => 0,
            ]);
            $heroes[$i] = $h;
        }

        $suggestions = app(InvestmentPlanner::class)->plan($user->defaultGameAccount, null, 20);
        $byHero = collect($suggestions)->keyBy('hero_id');

        // h0..h4 estão no pentagrama; h5 não
        $this->assertContains('in_pentagram', $byHero[$heroes[0]->id]['reasons']);
        $this->assertContains('in_pentagram', $byHero[$heroes[4]->id]['reasons']);
        $this->assertNotContains('in_pentagram', $byHero[$heroes[5]->id]['reasons']);

        // h0 e h5 têm SI=10 idêntico, ambos ascended, mesma faction, mesma classe.
        // A única diff é o nível (level 240 vs 100) e o pentagrama. SI 10→30 dá o mesmo
        // power gain pra ambos (não depende de level). Logo h0 (com multiplier 1.3 do
        // pentagrama) deve ter score MAIOR que h5.
        $h0Si = collect($suggestions)->first(fn ($s) => $s['hero_id'] === $heroes[0]->id && $s['kind'] === 'signature_item');
        $h5Si = collect($suggestions)->first(fn ($s) => $s['hero_id'] === $heroes[5]->id && $s['kind'] === 'signature_item');
        $this->assertNotNull($h0Si);
        $this->assertNotNull($h5Si);
        $this->assertGreaterThan($h5Si['score'], $h0Si['score']);
    }

    public function test_ascension_sem_pool_e_sempre_affordable(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();
        $hero = $this->makeHero('low', 'lightbearer', 'tank', 'legendary');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 100, 'ascension_value' => 1, 'ascension_code' => 'rare',
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        // Recursos zerados — mas ascension não bloqueia
        $resources = UserResource::create(['game_account_id' => $user->defaultGameAccount->id]);

        $suggestions = app(InvestmentPlanner::class)->plan($user->defaultGameAccount, null, 10, $resources);

        $ascSug = collect($suggestions)->firstWhere('kind', 'ascension');
        $this->assertNotNull($ascSug);
        $this->assertTrue($ascSug['affordable']);
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
