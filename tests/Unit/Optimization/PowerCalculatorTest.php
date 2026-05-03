<?php

namespace Tests\Unit\Optimization;

use App\Models\Artifact;
use App\Models\Beast;
use App\Models\Collection;
use App\Models\Hero;
use App\Models\HeroStat;
use App\Models\UserHero;
use App\Services\Optimization\PowerCalculator;
use PHPUnit\Framework\TestCase;

class PowerCalculatorTest extends TestCase
{
    private PowerCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new PowerCalculator;
    }

    private function makeUserHero(array $attrs): UserHero
    {
        $userHero = new UserHero;
        foreach ($attrs as $k => $v) {
            $userHero->{$k} = $v;
        }
        return $userHero;
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

        // 10*100 + 240 + 30*5 + 0*30 + 60*0.5 = 1000 + 240 + 150 + 0 + 30 = 1420
        $this->assertEqualsWithDelta(1420.0, $this->calc->forUserHero($uh), 0.001);
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

        // 16*100 + 240 + 40*5 + 9*30 + 100*0.5 + 100 (F3) + 200 (F9)
        // = 1600 + 240 + 200 + 270 + 50 + 300 = 2660
        $this->assertEqualsWithDelta(2660.0, $this->calc->forUserHero($uh), 0.001);
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

        $this->assertEqualsWithDelta(1420.0, $this->calc->forUserHero($uh), 0.001);
    }

    public function test_target_baseline_soma_stats_quando_hero_tem_stats(): void
    {
        $tier = new \App\Models\Tier(['code' => 'ascended']);
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
        $beast->strength_buff = 100;
        $beast->intelligence_buff = 80;
        $beast->agility_buff = 120;

        $this->assertSame(100.0, $this->calc->beastBuffForType($beast, 'strength'));
        $this->assertSame(80.0, $this->calc->beastBuffForType($beast, 'intelligence'));
        $this->assertSame(120.0, $this->calc->beastBuffForType($beast, 'agility'));
        $this->assertSame(0.0, $this->calc->beastBuffForType($beast, null));
        $this->assertSame(0.0, $this->calc->beastBuffForType(null, 'strength'));
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
        $beast->strength_buff = 200;     // 200 * 0.5 = 100
        $beast->intelligence_buff = 0;
        $beast->agility_buff = 0;

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
        $beast->strength_buff = 1000;    // só Force, hero é Agility
        $beast->intelligence_buff = 0;
        $beast->agility_buff = 0;

        // Sem buff aplicado: base = 1240
        $this->assertEqualsWithDelta(1240.0, $this->calc->forUserHero($uh, $hero, $beast), 0.001);
    }

    public function test_equipment_contribution_zero_quando_tier_ou_level_null(): void
    {
        $this->assertSame(0.0, $this->calc->equipmentContribution(null, 10));
        $this->assertSame(0.0, $this->calc->equipmentContribution('T3', null));
        $this->assertSame(0.0, $this->calc->equipmentContribution(null, null));
    }

    public function test_equipment_contribution_formula(): void
    {
        // tier_value * 10 + level * 2
        $this->assertEqualsWithDelta(12.0, $this->calc->equipmentContribution('T1', 1), 0.001);  // 10+2
        $this->assertEqualsWithDelta(50.0, $this->calc->equipmentContribution('T3', 10), 0.001); // 30+20
        $this->assertEqualsWithDelta(100.0, $this->calc->equipmentContribution('T6', 20), 0.001); // 60+40
    }

    public function test_equipment_contribution_clampa_level_acima_de_20(): void
    {
        // Level acima do max é clampado a 20
        $this->assertEqualsWithDelta(100.0, $this->calc->equipmentContribution('T6', 999), 0.001);
    }

    public function test_total_equipment_soma_quatro_slots(): void
    {
        $uh = $this->makeUserHero([
            'weapon_tier' => 'T6', 'weapon_level' => 20,   // 100
            'helmet_tier' => 'T3', 'helmet_level' => 10,   // 50
            'chest_tier' => 'T2', 'chest_level' => 5,      // 30
            'boots_tier' => null, 'boots_level' => null,   // 0
        ]);

        $this->assertEqualsWithDelta(180.0, $this->calc->totalEquipmentContribution($uh), 0.001);
    }

    public function test_for_user_hero_inclui_equipamento(): void
    {
        $uh = $this->makeUserHero([
            'ascension_value' => 10, 'level' => 240, 'signature_item' => 0,
            'furniture' => 0, 'engraving' => 0,
            'weapon_tier' => 'T6', 'weapon_level' => 20,   // 100
            'helmet_tier' => 'T3', 'helmet_level' => 10,   // 50
            'chest_tier' => null, 'chest_level' => null,
            'boots_tier' => null, 'boots_level' => null,
        ]);
        // base = 10*100 + 240 = 1240
        // + equipamento: 100 + 50 = 150
        // total: 1390
        $this->assertEqualsWithDelta(1390.0, $this->calc->forUserHero($uh), 0.001);
    }
}
