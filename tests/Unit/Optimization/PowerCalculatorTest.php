<?php

namespace Tests\Unit\Optimization;

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
}
