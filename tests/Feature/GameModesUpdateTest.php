<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroRating;
use App\Models\RecommendedFormation;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserResonatingCrystal;
use App\Services\Optimization\RecommendedFormationMatcher;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\DragonforgeTrialsSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\ResonatingCrystalCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Campanha com fases de vários grupos, Cristal Ressonante acima de 501 e as
 * Provas da Forja Dracônica (2026-10-18).
 */
class GameModesUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    private function makeHero(string $slug, string $faction = 'lightbearer'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $faction)->value('id'),
            'hero_class_id' => HeroClass::where('code', 'warrior')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    // ---------------- Campanha ----------------

    public function test_campanha_comeca_com_uma_equipe_e_aceita_ate_cinco(): void
    {
        $user = User::factory()->create();
        $campaign = ChallengeType::where('code', 'campaign')->sole();

        $this->assertSame(5, $campaign->max_teams);
        $this->assertTrue((bool) $campaign->teams_must_be_unique);

        $this->actingAs($user)->post('/formations', ['name' => 'Fase 60-2', 'challenge_type_id' => $campaign->id, 'mode' => 'target']);
        $this->assertSame(1, Formation::where('name', 'Fase 60-2')->sole()->teams_count);

        $this->actingAs($user)->post('/formations', ['name' => 'Fase 60-4', 'challenge_type_id' => $campaign->id, 'mode' => 'target', 'teams_count' => 5]);
        $multi = Formation::where('name', 'Fase 60-4')->sole();
        $this->assertSame(5, $multi->teams_count);
        $this->assertSame(5, $multi->teams()->count());
    }

    // ---------------- Cristal Ressonante ----------------

    public function test_cristal_aceita_os_numeros_de_fim_de_jogo(): void
    {
        $this->seed(ResonatingCrystalCostsSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/crystal', ['level' => 675, 'level_cap' => 1040, 'slots_unlocked' => 112, 'slots_occupied' => 106])
            ->assertOk()
            ->assertJsonPath('crystal.level', 675)
            ->assertJsonPath('crystal.level_cap', 1040)
            ->assertJsonPath('crystal.slots_occupied', 106);

        // Roster vazio no app, mas o nível informado vale: cristal evoluído, teto do jogo.
        $this->actingAs($user)
            ->get('/crystal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('crystal.effective_level', 675)
                ->where('crystal.is_upgraded', true)
                ->where('crystal.level_cap', 1040)
                ->where('crystal.slots_unlocked', 112)
                ->where('crystal.slots_occupied', 106)
                ->where('crystal.can_upgrade', true)
                ->where('crystal.cost_data_max_level', UserResonatingCrystal::COST_DATA_MAX_LEVEL)
                // Acima de 501 não há custo público — a tela avisa em vez de inventar.
                ->where('nextLevelCost', null));
    }

    public function test_cristal_valida_teto_e_posicoes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/crystal', ['level' => 675, 'level_cap' => 600])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('level_cap');

        $this->actingAs($user)
            ->patchJson('/crystal', ['slots_unlocked' => 112, 'slots_occupied' => 113])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slots_occupied');
    }

    public function test_cristal_volta_a_estimar_o_teto_quando_apagado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patchJson('/crystal', ['level' => 300, 'level_cap' => 1040])->assertOk();

        $this->actingAs($user)->patchJson('/crystal', ['level_cap' => null])->assertOk()->assertJsonPath('crystal.level_cap', null);

        // Sem teto informado e roster vazio: 240 + 5 × 0 Ascended.
        $this->assertSame(240, UserResonatingCrystal::levelCapForAccount($user->defaultGameAccount->fresh()));
    }

    // ---------------- Forja Dracônica ----------------

    public function test_forja_dracônica_tem_2_a_3_equipes_exclusivas(): void
    {
        $type = ChallengeType::where('code', 'dragon_trial')->sole();

        $this->assertSame('Provas da Forja Dracônica', $type->name_pt);
        $this->assertSame(2, $type->min_teams);
        $this->assertSame(3, $type->max_teams);
        $this->assertTrue((bool) $type->teams_must_be_unique);
    }

    public function test_seeder_cria_formacoes_com_equipes_e_ratings_derivados(): void
    {
        $shemira = $this->makeHero('shemira-the-soulreaver', 'graveborn');  // presente em quase todas
        $draconisFora = $this->makeHero('dragao-novo', 'draconis');           // dracônico fora do guia
        $outroFora = $this->makeHero('heroi-qualquer', 'mauler');             // não-dracônico fora do guia

        (new DragonforgeTrialsSeeder)->run();

        $formations = RecommendedFormation::where('mode', RecommendedFormation::MODE_DRAGONFORGE)->get();
        $this->assertCount(81, $formations);

        $stage3 = $formations->firstWhere('slug', 'dragonforge-f03-01');
        $this->assertCount(2, $stage3->teams);
        $stage7 = $formations->firstWhere('slug', 'dragonforge-f07-01');
        $this->assertCount(3, $stage7->teams);

        // Nenhuma formação repete herói entre equipes.
        foreach ($formations as $rf) {
            $all = array_merge(...$rf->teams);
            $this->assertSame(count($all), count(array_unique($all)), "{$rf->slug} repete herói");
        }

        $tier = fn (Hero $hero) => HeroRating::where(['hero_id' => $hero->id, 'mode' => HeroRating::MODE_DRAGONFORGE])->value('tier');
        $this->assertSame('S+', $tier($shemira));
        $this->assertSame('A', $tier($draconisFora));
        $this->assertSame('C', $tier($outroFora));
    }

    public function test_seeder_e_idempotente(): void
    {
        (new DragonforgeTrialsSeeder)->run();
        (new DragonforgeTrialsSeeder)->run();

        $this->assertSame(81, RecommendedFormation::where('mode', RecommendedFormation::MODE_DRAGONFORGE)->count());
    }

    public function test_copiar_comp_da_forja_cria_todas_as_equipes(): void
    {
        $user = User::factory()->create();
        foreach (['shemira-the-soulreaver', 'ivan', 'emilia', 'rem', 'hildwin', 'tamrus', 'solise-water-animator', 'liberta', 'naroko', 'nyla'] as $slug) {
            $this->makeHero($slug);
        }
        (new DragonforgeTrialsSeeder)->run();

        $this->actingAs($user)->post('/formations/recommended/dragonforge-f03-01/clone')->assertRedirect();

        $formation = Formation::with('teams.teamHeroes.hero', 'challengeType')->latest('id')->firstOrFail();
        $this->assertSame('dragon_trial', $formation->challengeType->code);
        $this->assertSame(2, $formation->teams_count);
        $this->assertSame(
            ['shemira-the-soulreaver', 'ivan', 'emilia', 'rem', 'hildwin'],
            $formation->teams->firstWhere('position', 1)->teamHeroes->sortBy('slot')->pluck('hero.slug')->all(),
        );
        $this->assertSame(5, $formation->teams->firstWhere('position', 2)->teamHeroes->count());
    }

    public function test_matcher_compara_com_a_equipe_mais_parecida(): void
    {
        $team = collect(['tamrus', 'liberta', 'naroko', 'nyla'])->map(fn ($slug) => $this->makeHero($slug))->all();
        (new DragonforgeTrialsSeeder)->run();

        $matches = (new RecommendedFormationMatcher)->match($team, HeroRating::MODE_DRAGONFORGE, 3, 10);
        $match = collect($matches)->firstWhere('slug', 'dragonforge-f03-01');

        $this->assertNotNull($match);
        $this->assertSame('Forja Dracônica · Fase 3 · Opção 1 · Equipe 2', $match['name']);
        $this->assertSame(4, $match['core_overlap']);
        $this->assertSame(5, $match['total_core']);
    }

    public function test_pagina_de_comps_envia_as_equipes(): void
    {
        (new DragonforgeTrialsSeeder)->run();

        $this->actingAs(User::factory()->create())
            ->get('/formations/recommended')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'recommendedFormations',
                fn ($list) => collect($list)->contains(fn ($rf) => $rf['slug'] === 'dragonforge-f07-01' && count($rf['teams']) === 3),
            ));
    }
}
