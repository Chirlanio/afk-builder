<?php

namespace Tests\Feature\Optimization;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Services\Optimization\PowerCalculator;
use App\Services\Optimization\ScoringProfile;
use App\Services\Optimization\TeamScorer;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamScorerTest extends TestCase
{
    use RefreshDatabase;

    private TeamScorer $scorer;

    private ScoringProfile $defaultProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);

        $this->scorer = new TeamScorer(new PowerCalculator);
        $this->defaultProfile = new ScoringProfile;
    }

    private function makeHero(string $slug, string $faction, string $class, string $tier = 'ascended'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $faction)->value('id'),
            'hero_class_id' => HeroClass::where('code', $class)->value('id'),
            'tier_id' => Tier::where('code', $tier)->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ])->load(['faction', 'heroClass', 'tier']);
    }

    public function test_pontua_5_stack_puro_com_bonus_maximo(): void
    {
        $heroes = [
            $this->makeHero('h1', 'lightbearer', 'tank'),
            $this->makeHero('h2', 'lightbearer', 'warrior'),
            $this->makeHero('h3', 'lightbearer', 'ranger'),
            $this->makeHero('h4', 'lightbearer', 'mage'),
            $this->makeHero('h5', 'lightbearer', 'support'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        $this->assertSame(1.0, $score->factionBonus);
        $this->assertStringContainsString('5x lightbearer', $score->details['faction_pattern']);
    }

    public function test_pontua_3_mais_2_de_duas_faccoes(): void
    {
        $heroes = [
            $this->makeHero('h1', 'mauler', 'tank'),
            $this->makeHero('h2', 'mauler', 'warrior'),
            $this->makeHero('h3', 'mauler', 'ranger'),
            $this->makeHero('h4', 'wilder', 'mage'),
            $this->makeHero('h5', 'wilder', 'support'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        $this->assertSame(0.6, $score->factionBonus);
        $this->assertStringContainsString('3+2', $score->details['faction_pattern']);
    }

    public function test_celestial_age_como_wildcard_para_5_stack(): void
    {
        $heroes = [
            $this->makeHero('h1', 'graveborn', 'tank'),
            $this->makeHero('h2', 'graveborn', 'warrior'),
            $this->makeHero('h3', 'graveborn', 'ranger'),
            $this->makeHero('h4', 'graveborn', 'mage'),
            $this->makeHero('h5', 'celestial', 'support'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        $this->assertSame(1.0, $score->factionBonus);
        $this->assertStringContainsString('5x graveborn', $score->details['faction_pattern']);
    }

    public function test_dimensional_nao_conta_para_5_stack(): void
    {
        $heroes = [
            $this->makeHero('h1', 'wilder', 'tank'),
            $this->makeHero('h2', 'wilder', 'warrior'),
            $this->makeHero('h3', 'wilder', 'ranger'),
            $this->makeHero('h4', 'wilder', 'mage'),
            $this->makeHero('h5', 'dimensional', 'support'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        $this->assertSame(0.0, $score->factionBonus);
        $this->assertNull($score->details['faction_pattern']);
    }

    public function test_hypogean_recebe_bonus_progressivo_independente_de_stack(): void
    {
        $heroes = [
            $this->makeHero('h1', 'hypogean', 'tank'),
            $this->makeHero('h2', 'hypogean', 'warrior'),
            $this->makeHero('h3', 'lightbearer', 'ranger'),
            $this->makeHero('h4', 'mauler', 'mage'),
            $this->makeHero('h5', 'wilder', 'support'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        $this->assertEqualsWithDelta(0.2, $score->factionBonus, 0.001);
        $this->assertArrayHasKey('hypogean_stack', $score->details['special_faction']);
    }

    public function test_draconis_ganha_self_advantage_com_2_ou_mais(): void
    {
        $heroes = [
            $this->makeHero('h1', 'draconis', 'tank'),
            $this->makeHero('h2', 'draconis', 'warrior'),
            $this->makeHero('h3', 'lightbearer', 'ranger'),
            $this->makeHero('h4', 'mauler', 'mage'),
            $this->makeHero('h5', 'wilder', 'support'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        $this->assertEqualsWithDelta(0.3, $score->factionBonus, 0.001);
        $this->assertArrayHasKey('draconis_self_advantage', $score->details['special_faction']);
    }

    public function test_time_sem_tank_e_sem_support_pontua_baixo_no_class_balance(): void
    {
        $heroes = [
            $this->makeHero('h1', 'lightbearer', 'warrior'),
            $this->makeHero('h2', 'lightbearer', 'warrior'),
            $this->makeHero('h3', 'lightbearer', 'ranger'),
            $this->makeHero('h4', 'lightbearer', 'ranger'),
            $this->makeHero('h5', 'lightbearer', 'mage'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        // sem tank/support → só conta variedade (3 classes * 0.05 = 0.15);
        // sem 4+ same class → sem penalidade; sem tank → sem balanced bonus.
        $this->assertEqualsWithDelta(0.15, $score->classBalance, 0.001);
    }

    public function test_time_com_4_da_mesma_classe_sofre_penalidade(): void
    {
        // 4 rangers + 1 tank = over-stack (max=4) → penalidade -0.15
        $heroes = [
            $this->makeHero('h1', 'lightbearer', 'tank'),
            $this->makeHero('h2', 'lightbearer', 'ranger'),
            $this->makeHero('h3', 'lightbearer', 'ranger'),
            $this->makeHero('h4', 'lightbearer', 'ranger'),
            $this->makeHero('h5', 'lightbearer', 'ranger'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        // tank(0.3) + 0 support + 2 unique * 0.05 + 0 balanced (precisa 3 unique) - 0.15 over-stack = 0.25
        $this->assertEqualsWithDelta(0.25, $score->classBalance, 0.001);
    }

    public function test_time_balanceado_pontua_alto_no_class_balance(): void
    {
        $heroes = [
            $this->makeHero('h1', 'lightbearer', 'tank'),
            $this->makeHero('h2', 'lightbearer', 'warrior'),
            $this->makeHero('h3', 'lightbearer', 'ranger'),
            $this->makeHero('h4', 'lightbearer', 'mage'),
            $this->makeHero('h5', 'lightbearer', 'support'),
        ];

        $score = $this->scorer->score($heroes, [], [], $this->defaultProfile);

        // tank(0.3) + support(0.3) + 5 unique * 0.05 + balanced bonus(0.10, 3+ unique com tank)
        // = 0.95
        $this->assertEqualsWithDelta(0.95, $score->classBalance, 0.001);
    }

    public function test_par_com_synergia_incrementa_o_synergy_score(): void
    {
        $h1 = $this->makeHero('lucius', 'lightbearer', 'tank');
        $h2 = $this->makeHero('rosaline', 'lightbearer', 'support');
        $h3 = $this->makeHero('outro', 'mauler', 'warrior');

        $weights = [TeamScorer::pairKey($h1->id, $h2->id) => 15];

        $score = $this->scorer->score([$h1, $h2, $h3], [], $weights, $this->defaultProfile);

        $this->assertEqualsWithDelta(1.5, $score->synergy, 0.001);
    }

    public function test_total_combina_componentes_com_pesos_do_profile(): void
    {
        $heroes = [
            $this->makeHero('h1', 'lightbearer', 'tank'),
            $this->makeHero('h2', 'lightbearer', 'warrior'),
            $this->makeHero('h3', 'lightbearer', 'ranger'),
            $this->makeHero('h4', 'lightbearer', 'mage'),
            $this->makeHero('h5', 'lightbearer', 'support'),
        ];

        $profile = new ScoringProfile(
            factionBonusWeight: 2.0,
            classBalanceWeight: 0.5,
            synergyWeight: 1.0,
            powerWeight: 0.0,
        );

        $score = $this->scorer->score($heroes, [], [], $profile);

        // faction(1.0)*2.0 + class(0.95)*0.5 + synergy(0)*1.0 + power(0)*0.0 = 2.475
        $this->assertEqualsWithDelta(2.475, $score->total, 0.001);
    }
}
