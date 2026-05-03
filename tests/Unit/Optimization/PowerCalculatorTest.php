<?php

namespace Tests\Unit\Optimization;

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
}
