<?php

namespace Tests\Unit\Optimization;

use App\Models\Artifact;
use App\Models\Beast;
use App\Models\Collection;
use App\Models\Equipment;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroStat;
use App\Models\SignatureItemCost;
use App\Models\Tier;
use App\Models\UserHero;
use App\Services\Optimization\PowerCalculator;
use PHPUnit\Framework\TestCase;

class PowerCalculatorTest extends TestCase
{
    private PowerCalculator $calc;

    /**
     * Cumulative SI cost dos dados reais do seeder. Pre-injetado pra evitar
     * acesso ao DB (este é unit test puro).
     *
     * @var array<int, int>
     */
    private const SI_CUMULATIVE = [
        0 => 0, 1 => 10, 2 => 20, 3 => 30, 4 => 40, 5 => 55,
        6 => 70, 7 => 85, 8 => 110, 9 => 140, 10 => 190,
        11 => 200, 12 => 210, 13 => 225, 14 => 240, 15 => 260,
        16 => 285, 17 => 310, 18 => 340, 19 => 380, 20 => 420,
        21 => 430, 22 => 450, 23 => 470, 24 => 490, 25 => 520,
        26 => 550, 27 => 590, 28 => 630, 29 => 670, 30 => 720,
        31 => 770, 32 => 820, 33 => 870, 34 => 930, 35 => 1000,
        36 => 1080, 37 => 1170, 38 => 1290, 39 => 1410, 40 => 1530,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new PowerCalculator;
        SignatureItemCost::primeCumulativeCacheForTesting(self::SI_CUMULATIVE);
    }

    protected function tearDown(): void
    {
        SignatureItemCost::flushCumulativeCache();
        parent::tearDown();
    }

    private function makeUserHero(array $attrs): UserHero
    {
        $userHero = new UserHero;
        foreach ($attrs as $k => $v) {
            $userHero->{$k} = $v;
        }

        return $userHero;
    }

    /**
     * Builda Equipment in-memory (sem DB) com defaults sane pros tests unitários.
     */
    private function makeEquipment(array $attrs): Equipment
    {
        $defaults = [
            'slug' => 'test-equipment',
            'name' => 'Test Equipment',
            'name_pt' => 'Equipamento de Teste',
            'set_code' => 'test',
            'slot' => 'boots',
            'material' => 'plate',
            'hero_type' => 'strength',
            'faction_id' => 1,
            'quality_tier' => 'mythic',
            'power_rating' => 1000,
            'stats' => [],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        $equipment = new Equipment(array_merge($defaults, $attrs));

        return $equipment;
    }

    public function test_formula_basica_sem_furniture(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10,
            'level' => 240,
            'signature_item' => 30,
            'furniture' => 0,
            'engraving' => 60,
        ]);

        // 10*100 + 240 + SI(30)=720*0.2=144 + 0*30 + 60*0.5 = 1000 + 240 + 144 + 0 + 30 = 1414
        // + engraving milestones E30 (+50) + E60 (+100) = +150 → 1564
        $this->assertEqualsWithDelta(1564.0, $this->calc->forUserHero($uh), 0.001);
    }

    public function test_furniture_3_adiciona_bonus_f3(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 12,
            'level' => 240,
            'signature_item' => 0,
            'furniture' => 3,
            'engraving' => 0,
        ]);

        // 12*100 + 240 + 0 + 3*30 + 0 + 100 (F3 bonus) = 1200 + 240 + 90 + 100 = 1630
        $this->assertEqualsWithDelta(1630.0, $this->calc->forUserHero($uh), 0.001);
    }

    public function test_furniture_9_adiciona_bonus_f3_e_f9_cumulativo(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 16,
            'level' => 240,
            'signature_item' => 40,
            'furniture' => 9,
            'engraving' => 100,
        ]);

        // 16*100 + 240 + SI(40)=1530*0.2=306 + 9*30 + 100*0.5 + 100 (F3) + 200 (F9)
        // = 1600 + 240 + 306 + 270 + 50 + 300 = 2766
        // + engraving milestones E30+E60+E80 = 50+100+50 = 200 → 2966
        $this->assertEqualsWithDelta(2966.0, $this->calc->forUserHero($uh), 0.001);
    }

    public function test_furniture_entre_3_e_9_so_aplica_f3(): void
    {
        $a = $this->makeUserHero([
            'ascension_value' => 12, 'level' => 240, 'signature_item' => 0,
            'furniture' => 3, 'engraving' => 0,
        ]);
        $b = $this->makeUserHero([
            'ascension_value' => 12, 'level' => 240, 'signature_item' => 0,
            'furniture' => 8, 'engraving' => 0,
        ]);

        // F3 ativo em ambos, F9 só ativa quando furniture >= 9
        // a: 1200 + 240 + 90 + 100 = 1630
        // b: 1200 + 240 + 240 + 100 = 1780
        $this->assertEqualsWithDelta(1630.0, $this->calc->forUserHero($a), 0.001);
        $this->assertEqualsWithDelta(1780.0, $this->calc->forUserHero($b), 0.001);
    }

    public function test_stat_contribution_zero_quando_stats_null(): void
    {
        $this->assertSame(0.0, $this->calc->statContribution(null));
    }

    public function test_stat_contribution_soma_pesos(): void
    {
        $stats = new HeroStat;
        $stats->hp = 1500;          // *0.1 = 150
        $stats->attack = 200;       // *1.0 = 200
        $stats->defense = 100;      // *0.7 = 70
        $stats->crit_rate = 50;     // *0.3 = 15
        $stats->crit_amp = 50;      // *0.3 = 15
        $stats->accuracy = 50;      // *0.2 = 10
        $stats->dodge = 50;         // *0.2 = 10
        $stats->haste = 50;         // *0.2 = 10
        // Total: 480
        $this->assertEqualsWithDelta(480.0, $this->calc->statContribution($stats), 0.001);
    }

    public function test_stat_contribution_lida_com_campos_null(): void
    {
        $stats = new HeroStat;
        $stats->hp = 1000;
        $stats->attack = 100;
        // demais null
        // 1000*0.1 + 100*1.0 = 200
        $this->assertEqualsWithDelta(200.0, $this->calc->statContribution($stats), 0.001);
    }

    public function test_for_user_hero_soma_stats_quando_hero_e_stats_presentes(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
        ]);
        // base = 10*100 + 240 = 1240

        $stats = new HeroStat;
        $stats->hp = 1000;          // *0.1 = 100
        $stats->attack = 200;       // *1.0 = 200
        // contribution = 300

        $hero = new Hero;
        $hero->setRelation('stats', $stats);

        $this->assertEqualsWithDelta(1540.0, $this->calc->forUserHero($uh, $hero), 0.001);
    }

    public function test_for_user_hero_sem_hero_mantem_calculo_antigo(): void
    {
        // backward-compat: chamar sem o segundo parâmetro deve retornar a fórmula original
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 30,
            'furniture' => 0, 'engraving' => 60,
        ]);

        // Mesma fórmula do test_formula_basica: SI 30 cumulativo = 720 × 0.2 = 144,
        // engraving 60 = 30 (linear) + 150 (milestones E30+E60) → 1564.
        $this->assertEqualsWithDelta(1564.0, $this->calc->forUserHero($uh), 0.001);
    }

    public function test_si_salto_30_para_40_reflete_custo_real(): void
    {
        // SI 30: 720 emblems × 0.2 = 144
        $si30 = $this->makeUserHero([
            'ascension_value' => 0, 'level' => 0, 'signature_item' => 30,
            'furniture' => 0, 'engraving' => 0,
        ]);
        // SI 40: 1530 emblems × 0.2 = 306
        $si40 = $this->makeUserHero([
            'ascension_value' => 0, 'level' => 0, 'signature_item' => 40,
            'furniture' => 0, 'engraving' => 0,
        ]);

        // Diferença SI 30→40 deve ser ~162 (1530-720)*0.2, refletindo os
        // 1050 emblems a mais (faction emblems Celestial/Hypogean only).
        // Antes do refactor (linear *5), a diferença era apenas 50 (10*5).
        $diff = $this->calc->forUserHero($si40) - $this->calc->forUserHero($si30);
        $this->assertEqualsWithDelta(162.0, $diff, 0.5);
    }

    public function test_target_baseline_soma_stats_quando_hero_tem_stats(): void
    {
        $tier = new Tier(['code' => 'ascended']);
        $hero = new Hero;
        $hero->setRelation('tier', $tier);

        $stats = new HeroStat;
        $stats->hp = 2000;          // *0.1 = 200
        $stats->attack = 300;       // *1.0 = 300
        $stats->defense = 200;      // *0.7 = 140
        $hero->setRelation('stats', $stats);

        // baseline ascended: 12*100 + 240 = 1440
        // + stats: 200 + 300 + 140 = 640
        // total: 2080
        $this->assertEqualsWithDelta(2080.0, $this->calc->targetBaseline($hero), 0.001);
    }

    public function test_artifact_contribution_zero_quando_null(): void
    {
        $this->assertSame(0.0, $this->calc->artifactContribution(null, 1));
    }

    public function test_artifact_contribution_base_por_estrelas(): void
    {
        $artifact = new Artifact;
        $artifact->attributes = []; // sem atributos extras

        // 1★=50, 3★=150, 5★=250
        $this->assertEqualsWithDelta(50.0, $this->calc->artifactContribution($artifact, 1), 0.001);
        $this->assertEqualsWithDelta(150.0, $this->calc->artifactContribution($artifact, 3), 0.001);
        $this->assertEqualsWithDelta(250.0, $this->calc->artifactContribution($artifact, 5), 0.001);
    }

    public function test_artifact_contribution_soma_atributos_numericos(): void
    {
        $artifact = new Artifact;
        $artifact->attributes = [
            ['stat' => 'HP', 'value' => '8.00%'],     // 8 * 2 = 16
            ['stat' => 'CRIT', 'value' => '5.00'],    // 5 * 2 = 10
            ['stat' => 'HASTE', 'value' => '10.00'],  // 10 * 2 = 20
        ];

        // 3★ base: 150 + 16 + 10 + 20 = 196
        $this->assertEqualsWithDelta(196.0, $this->calc->artifactContribution($artifact, 3), 0.001);
    }

    public function test_collection_contribution_zero_quando_null(): void
    {
        $this->assertSame(0.0, $this->calc->collectionContribution(null));
    }

    public function test_collection_contribution_soma_bonus(): void
    {
        $collection = new Collection;
        $collection->hp_bonus = 1000;       // *0.1 = 100
        $collection->attack_bonus = 200;    // *1.0 = 200
        $collection->defense_bonus = 100;   // *0.7 = 70
        // total: 370
        $this->assertEqualsWithDelta(370.0, $this->calc->collectionContribution($collection), 0.001);
    }

    public function test_beast_buff_for_type(): void
    {
        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 50.0, 'defense' => 50.0];   // soma 100
        $beast->buffs_intelligence = ['attack' => 80.0];                  // soma 80
        $beast->buffs_agility = ['attack' => 120.0];                      // soma 120
        $beast->buffs_team = null;

        // Level 36 = factor 1.0 (default)
        $this->assertEqualsWithDelta(100.0, $this->calc->beastBuffForType($beast, 'strength'), 0.001);
        $this->assertEqualsWithDelta(80.0, $this->calc->beastBuffForType($beast, 'intelligence'), 0.001);
        $this->assertEqualsWithDelta(120.0, $this->calc->beastBuffForType($beast, 'agility'), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->calc->beastBuffForType($beast, null), 0.001);
        $this->assertSame(0.0, $this->calc->beastBuffForType(null, 'strength'));
    }

    public function test_beast_buff_inclui_team_scope(): void
    {
        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 50.0];
        $beast->buffs_team = ['hp' => 20.0, 'defense' => 30.0];   // soma 50

        // Strength scope (50) + Team scope (50) = 100
        $this->assertEqualsWithDelta(100.0, $this->calc->beastBuffForType($beast, 'strength'), 0.001);
        // Hero sem type ainda recebe team scope
        $this->assertEqualsWithDelta(50.0, $this->calc->beastBuffForType($beast, null), 0.001);
    }

    public function test_beast_buff_escala_pelo_level(): void
    {
        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 100.0];

        // Level 36 = 100% dos buffs
        $this->assertEqualsWithDelta(100.0, $this->calc->beastBuffForType($beast, 'strength', 36), 0.001);
        // Level 18 = 50%
        $this->assertEqualsWithDelta(50.0, $this->calc->beastBuffForType($beast, 'strength', 18), 0.001);
        // Level 1 = ~2.78%
        $this->assertEqualsWithDelta(2.7777, $this->calc->beastBuffForType($beast, 'strength', 1), 0.001);
    }

    public function test_for_user_hero_aplica_beast_buff_factor_50(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
        ]);
        // base = 10*100 + 240 = 1240

        $hero = new Hero;
        $hero->type = 'strength';

        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 200.0];   // soma 200, * 0.5 (BEAST_BUFF_FACTOR) = 100

        $this->assertEqualsWithDelta(1340.0, $this->calc->forUserHero($uh, $hero, $beast), 0.001);
    }

    public function test_for_user_hero_ignora_beast_quando_tipo_nao_bate(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
        ]);

        $hero = new Hero;
        $hero->type = 'agility';

        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 1000.0];   // só strength, hero é agility
        $beast->buffs_team = null;

        // Sem buff aplicado: base = 1240
        $this->assertEqualsWithDelta(1240.0, $this->calc->forUserHero($uh, $hero, $beast), 0.001);
    }

    public function test_for_user_hero_aplica_beast_tier_factor_s_plus(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
        ]);
        $hero = new Hero;
        $hero->type = 'strength';

        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 200.0];
        $beast->tier = 'S+';   // factor 1.0 (em vez do default 0.5)

        // base 1240 + 200 * 1.0 = 1440
        $this->assertEqualsWithDelta(1440.0, $this->calc->forUserHero($uh, $hero, $beast), 0.001);
    }

    public function test_for_user_hero_aplica_beast_tier_factor_c(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
        ]);
        $hero = new Hero;
        $hero->type = 'strength';

        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 200.0];
        $beast->tier = 'C';    // factor 0.4

        // base 1240 + 200 * 0.4 = 1320
        $this->assertEqualsWithDelta(1320.0, $this->calc->forUserHero($uh, $hero, $beast), 0.001);
    }

    public function test_beast_sem_tier_usa_factor_default_50(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
        ]);
        $hero = new Hero;
        $hero->type = 'strength';

        $beast = new Beast;
        $beast->buffs_strength = ['attack' => 200.0];
        $beast->tier = null;   // sem tier → factor default 0.5

        // base 1240 + 200 * 0.5 = 1340
        $this->assertEqualsWithDelta(1340.0, $this->calc->forUserHero($uh, $hero, $beast), 0.001);
    }

    public function test_engraving_milestone_bonus_aplica_em_thresholds(): void
    {
        $this->assertSame(0.0, $this->calc->engravingMilestoneBonus(0));
        $this->assertSame(0.0, $this->calc->engravingMilestoneBonus(29));
        // E30: +50
        $this->assertSame(50.0, $this->calc->engravingMilestoneBonus(30));
        $this->assertSame(50.0, $this->calc->engravingMilestoneBonus(59));
        // E60: cumulativo +100 (total 150)
        $this->assertSame(150.0, $this->calc->engravingMilestoneBonus(60));
        $this->assertSame(150.0, $this->calc->engravingMilestoneBonus(79));
        // E80: cumulativo +50 (total 200)
        $this->assertSame(200.0, $this->calc->engravingMilestoneBonus(80));
        $this->assertSame(200.0, $this->calc->engravingMilestoneBonus(100));
    }

    public function test_engraving_milestone_label(): void
    {
        $this->assertNull(PowerCalculator::engravingMilestoneLabel(0));
        $this->assertNull(PowerCalculator::engravingMilestoneLabel(29));
        $this->assertSame('E30', PowerCalculator::engravingMilestoneLabel(30));
        $this->assertSame('E30', PowerCalculator::engravingMilestoneLabel(59));
        $this->assertSame('E60', PowerCalculator::engravingMilestoneLabel(60));
        $this->assertSame('E60', PowerCalculator::engravingMilestoneLabel(79));
        $this->assertSame('E80', PowerCalculator::engravingMilestoneLabel(80));
        $this->assertSame('E80', PowerCalculator::engravingMilestoneLabel(100));
    }

    public function test_has_real_stats_detecta_hp_acima_do_limiar(): void
    {
        // HP=0 ou < 1000 → false
        $empty = $this->makeUserHero(['hp' => 0]);
        $low = $this->makeUserHero(['hp' => 999]);
        $threshold = $this->makeUserHero(['hp' => 1000]);
        $real = $this->makeUserHero(['hp' => 642254]); // Shemira Lendário

        $this->assertFalse($this->calc->hasRealStats($empty));
        $this->assertFalse($this->calc->hasRealStats($low));
        $this->assertFalse($this->calc->hasRealStats($threshold)); // 1000 não passa, precisa > 1000
        $this->assertTrue($this->calc->hasRealStats($real));
    }

    public function test_real_stat_contribution_calibrado_para_snapshots(): void
    {
        // Snapshots empíricos do jogo (PT-BR, versão 2026)

        // Shemira Lendário (Graveborn Mage)
        $shemira = $this->makeUserHero([
            'hp' => 642254, 'attack' => 41552, 'defense' => 9239,
            'crit_rate' => 5, 'accuracy' => 65, 'dodge' => 0, 'haste' => 14,
        ]);
        // 642254*0.0005 + 41552*0.005 + 9239*0.005 + 5*0.3 + 65*0.2 + 0 + 14*0.2
        // = 321.127 + 207.76 + 46.195 + 1.5 + 13 + 0 + 2.8 = 592.382
        $this->assertEqualsWithDelta(592.4, $this->calc->realStatContribution($shemira), 0.5);

        // Baden Lendário (Celestial Warrior)
        $baden = $this->makeUserHero([
            'hp' => 890481, 'attack' => 75446, 'defense' => 13374,
            'crit_rate' => 5, 'accuracy' => 60, 'dodge' => 50, 'haste' => 14,
        ]);
        // 445.24 + 377.23 + 66.87 + 1.5 + 12 + 10 + 2.8 = 915.64
        $this->assertEqualsWithDelta(915.6, $this->calc->realStatContribution($baden), 0.5);

        // Ivan Elite (Lightbearer Tank)
        $ivan = $this->makeUserHero([
            'hp' => 995903, 'attack' => 69194, 'defense' => 14051,
            'crit_rate' => 5, 'accuracy' => 0, 'dodge' => 35, 'haste' => 20,
        ]);
        // 497.95 + 345.97 + 70.26 + 1.5 + 0 + 7 + 4 = 926.68
        $this->assertEqualsWithDelta(926.7, $this->calc->realStatContribution($ivan), 0.5);

        // Insight do snapshot: warrior > mage por stats reais (HP+ATK maiores),
        // tank Elite ≈ warrior Lendário (HP maior, ATK menor — empate na prática).
        $this->assertGreaterThan(
            $this->calc->realStatContribution($shemira),
            $this->calc->realStatContribution($baden),
        );
        // Baden e Ivan estão dentro de ~1% — não força ordenação rígida
        $this->assertEqualsWithDelta(
            $this->calc->realStatContribution($baden),
            $this->calc->realStatContribution($ivan),
            20.0,
        );
    }

    public function test_for_user_hero_prefere_stats_reais_quando_preenchidos(): void
    {
        // UserHero com stats reais (Shemira Lendário, ascensão 6, level 160)
        $uh = $this->makeUserHero([
            'ascension_value' => 6, 'level' => 160, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
            'hp' => 642254, 'attack' => 41552, 'defense' => 9239,
            'crit_rate' => 5, 'accuracy' => 65, 'haste' => 14,
        ]);

        // Hero do catálogo com stats DIFERENTES (não devem ser usados quando real stats presentes)
        $stats = new HeroStat;
        $stats->hp = 99999;
        $stats->attack = 99999;
        $hero = new Hero;
        $hero->setRelation('stats', $stats);

        // base = 6*100 + 160 = 760
        // + realStatContribution(Shemira) ~= 592
        // Total ~= 1352
        $power = $this->calc->forUserHero($uh, $hero);
        $this->assertEqualsWithDelta(1352.4, $power, 1.0);

        // Garante que NÃO somou o HeroStat (que daria 99999*0.1+99999*1 ~= 110k)
        $this->assertLessThan(2000.0, $power);
    }

    public function test_for_user_hero_cai_para_heroStat_quando_sem_real_stats(): void
    {
        // UserHero SEM stats reais (hp=0)
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
            'hp' => 0, 'attack' => 0,
        ]);

        $stats = new HeroStat;
        $stats->hp = 1000;    // *0.1 = 100
        $stats->attack = 200; // *1.0 = 200
        $hero = new Hero;
        $hero->setRelation('stats', $stats);

        // base 1240 + heroStat 300 = 1540 (mesma fórmula antiga)
        $this->assertEqualsWithDelta(1540.0, $this->calc->forUserHero($uh, $hero), 0.001);
    }

    public function test_baden_vs_thane_stats_iguais_geram_power_igual(): void
    {
        // Insight empírico: Baden e Thane (ambos warriors no Lendário) têm stats
        // virtualmente idênticos no jogo (~0.02% diff). Verificamos que o calculator
        // reflete isso — sem multipliers de hero-específicos que quebrem a regra.
        $baden = $this->makeUserHero([
            'ascension_value' => 6, 'level' => 160,
            'hp' => 890481, 'attack' => 75446, 'defense' => 13374,
            'crit_rate' => 5, 'accuracy' => 60, 'dodge' => 50, 'haste' => 14,
        ]);
        $thane = $this->makeUserHero([
            'ascension_value' => 6, 'level' => 160,
            'hp' => 890628, 'attack' => 75438, 'defense' => 13372,
            'crit_rate' => 5, 'accuracy' => 60, 'dodge' => 50, 'haste' => 0,
        ]);

        // Diff esperado ~= 0.6 (variação de stats + diff haste de 14 vs 0 = 2.8)
        $diff = abs($this->calc->forUserHero($baden) - $this->calc->forUserHero($thane));
        $this->assertLessThan(5.0, $diff);
    }

    public function test_ivan_elite_para_elite_plus_diff_minima(): void
    {
        // Insight: estágios incrementais (Elite → Elite+) dão ganho minúsculo (<1%).
        $elite = $this->makeUserHero([
            'ascension_value' => 4, 'level' => 100,
            'hp' => 995903, 'attack' => 69194, 'defense' => 14051,
            'crit_rate' => 5, 'dodge' => 35, 'haste' => 20,
        ]);
        $elitePlus = $this->makeUserHero([
            'ascension_value' => 5, 'level' => 100,
            'hp' => 997282, 'attack' => 69292, 'defense' => 14076,
            'crit_rate' => 5, 'dodge' => 35, 'haste' => 20,
        ]);

        // Stats sobem ~0.14%. Power total deve subir só pelo ascension+1 (+100)
        // mais ~1 ponto dos stats reais.
        $diff = $this->calc->forUserHero($elitePlus) - $this->calc->forUserHero($elite);
        // Esperado ~101 (100 do asc + 1 do stat). Tolerância ampla.
        $this->assertEqualsWithDelta(101.0, $diff, 2.0);
    }

    public function test_mythic_equipment_contribution_sem_itens_retorna_zero(): void
    {
        $uh = $this->makeUserHero(['hp' => 0]);
        $this->assertSame(0.0, $this->calc->mythicEquipmentContribution($uh));
    }

    public function test_mythic_equipment_contribution_soma_stats_de_um_item(): void
    {
        // Grevas de Gaius L0 — hp=36500, accuracy=60, haste=5, physical_resistance_pct=5
        // Power esperado: 36500*0.0005 + 60*0.2 + 5*0.2 + 5*0.5
        //               = 18.25 + 12 + 1 + 2.5 = 33.75
        $equipment = $this->makeEquipment([
            'slot' => 'boots',
            'faction_id' => 1,
            'stats' => ['hp' => 36500, 'accuracy' => 60, 'haste' => 5, 'physical_resistance_pct' => 5],
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
        ]);

        $uh = $this->makeUserHero(['boots_equipment_id' => 1, 'boots_equipment_level' => 0]);
        $uh->setRelation('bootsEquipment', $equipment);

        $this->assertEqualsWithDelta(33.75, $this->calc->mythicEquipmentContribution($uh), 0.001);
    }

    public function test_mythic_equipment_contribution_aplica_faction_bonus(): void
    {
        // Grevas de Gaius L0, faction Lightbearer (id=1).
        // Hero também Lightbearer → bonus 30%: 33.75 * 1.30 = 43.875
        $equipment = $this->makeEquipment([
            'slot' => 'boots',
            'faction_id' => 1,
            'stats' => ['hp' => 36500, 'accuracy' => 60, 'haste' => 5, 'physical_resistance_pct' => 5],
        ]);

        $uh = $this->makeUserHero(['boots_equipment_id' => 1, 'boots_equipment_level' => 0]);
        $uh->setRelation('bootsEquipment', $equipment);

        $faction = new Faction(['code' => 'lightbearer']);
        $faction->id = 1;
        $hero = new Hero;
        $hero->setRelation('faction', $faction);

        $this->assertEqualsWithDelta(43.875, $this->calc->mythicEquipmentContribution($uh, $hero), 0.001);
    }

    public function test_mythic_equipment_contribution_sem_faction_match_ignora_bonus(): void
    {
        // Equipment de Lightbearer, mas hero é Mauler (diff faction) → sem bonus
        $equipment = $this->makeEquipment([
            'slot' => 'boots',
            'faction_id' => 1, // lightbearer
            'stats' => ['hp' => 36500],
        ]);

        $uh = $this->makeUserHero(['boots_equipment_id' => 1, 'boots_equipment_level' => 0]);
        $uh->setRelation('bootsEquipment', $equipment);

        $faction = new Faction(['code' => 'mauler']);
        $faction->id = 2; // mauler != lightbearer
        $hero = new Hero;
        $hero->setRelation('faction', $faction);

        // hp 36500 * 0.0005 = 18.25 (sem bonus)
        $this->assertEqualsWithDelta(18.25, $this->calc->mythicEquipmentContribution($uh, $hero), 0.001);
    }

    public function test_mythic_equipment_contribution_escala_com_upgrade_level(): void
    {
        // L5 = 1.5x → 36500 * 1.5 * 0.0005 = 27.375
        $equipment = $this->makeEquipment([
            'slot' => 'boots',
            'faction_id' => 1,
            'stats' => ['hp' => 36500],
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
        ]);

        $uh = $this->makeUserHero(['boots_equipment_id' => 1, 'boots_equipment_level' => 5]);
        $uh->setRelation('bootsEquipment', $equipment);

        $this->assertEqualsWithDelta(27.375, $this->calc->mythicEquipmentContribution($uh), 0.001);
    }

    public function test_mythic_equipment_contribution_soma_quatro_slots(): void
    {
        $boots = $this->makeEquipment(['slot' => 'boots', 'faction_id' => 1, 'stats' => ['hp' => 10000]]);
        $helmet = $this->makeEquipment(['slot' => 'helmet', 'faction_id' => 1, 'stats' => ['hp' => 10000]]);
        $chest = $this->makeEquipment(['slot' => 'chest', 'faction_id' => 1, 'stats' => ['hp' => 10000]]);
        $weapon = $this->makeEquipment(['slot' => 'weapon', 'faction_id' => 1, 'stats' => ['attack' => 1000]]);

        $uh = $this->makeUserHero([
            'boots_equipment_id' => 1, 'boots_equipment_level' => 0,
            'helmet_equipment_id' => 2, 'helmet_equipment_level' => 0,
            'chest_equipment_id' => 3, 'chest_equipment_level' => 0,
            'weapon_equipment_id' => 4, 'weapon_equipment_level' => 0,
        ]);
        $uh->setRelation('bootsEquipment', $boots);
        $uh->setRelation('helmetEquipment', $helmet);
        $uh->setRelation('chestEquipment', $chest);
        $uh->setRelation('weaponEquipment', $weapon);

        // hp: 30000 * 0.0005 = 15
        // attack: 1000 * 0.005 = 5
        // total = 20
        $this->assertEqualsWithDelta(20.0, $this->calc->mythicEquipmentContribution($uh), 0.001);
    }

    public function test_for_user_hero_inclui_mythic_equipment_contribution(): void
    {
        $equipment = $this->makeEquipment([
            'slot' => 'boots',
            'faction_id' => 1,
            'stats' => ['hp' => 36500, 'haste' => 5],
        ]);

        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
            'boots_equipment_id' => 1, 'boots_equipment_level' => 0,
        ]);
        $uh->setRelation('bootsEquipment', $equipment);

        // base = 10*100 + 240 = 1240
        // + mythic: 36500*0.0005 + 5*0.2 = 18.25 + 1 = 19.25
        $this->assertEqualsWithDelta(1259.25, $this->calc->forUserHero($uh), 0.001);
    }

    public function test_for_user_hero_inclui_engraving_milestone_bonus(): void
    {
        // Hero idêntico exceto pelo engraving — verifica que cruzar 30 dispara o salto
        $eng29 = $this->makeUserHero([
            'ascension_value' => 0, 'level' => 0, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 29,
        ]);
        $eng30 = $this->makeUserHero([
            'ascension_value' => 0, 'level' => 0, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 30,
        ]);

        // eng29: 0 + 29*0.5 = 14.5
        // eng30: 0 + 30*0.5 + 50 (E30) = 65
        // diff: 50.5 (= 50 do milestone + 0.5 linear)
        $diff = $this->calc->forUserHero($eng30) - $this->calc->forUserHero($eng29);
        $this->assertEqualsWithDelta(50.5, $diff, 0.001);
    }
}
