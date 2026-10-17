<?php

namespace Tests\Feature\Optimization;

use App\Models\Equipment;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\UserHero;
use App\Services\Optimization\PowerCalculator;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\FactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guarda de escala com os dados reais do EquipmentSeeder: o equipamento não pode
 * dominar o power. Quando os set bonuses T3/T4 entravam no cálculo, um set T3
 * completo valia ~11k — quase 4 heróis Ascended no máximo (~2,65k).
 */
class MythicEquipmentPowerScaleTest extends TestCase
{
    use RefreshDatabase;

    private PowerCalculator $calc;

    private Hero $hero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, EquipmentSeeder::class]);

        $this->calc = new PowerCalculator;
        $faction = Faction::where('code', 'lightbearer')->sole();
        $this->hero = new Hero(['faction_id' => $faction->id]);
        $this->hero->setRelation('faction', $faction);
    }

    private function fullSet(string $setCode, string $tier): UserHero
    {
        $userHero = new UserHero;
        foreach (Equipment::SLOTS as $slot) {
            $equipment = Equipment::where([
                'set_code' => $setCode,
                'slot' => $slot,
                'quality_tier' => $tier,
                'faction_id' => $this->hero->faction_id,
            ])->sole();
            $userHero->setRelation($slot.'Equipment', $equipment);
            $userHero->{$slot.'_equipment_level'} = $equipment->max_upgrade_level;
        }

        return $userHero;
    }

    public function test_set_t3_completo_vale_menos_que_um_heroi_ascended_no_maximo(): void
    {
        $maxedHero = $this->calc->forUserHero(new UserHero([
            'ascension_value' => 16, 'level' => 240, 'signature_item' => 30,
            'furniture' => 9, 'engraving' => 80,
        ]), $this->hero);

        foreach (['gaius', 'ancelot', 'mystic'] as $set) {
            $equipment = $this->calc->mythicEquipmentContribution($this->fullSet($set, Equipment::QUALITY_MYTHIC_T3), $this->hero);

            $this->assertLessThan($maxedHero, $equipment, "Set {$set} T3 completo ({$equipment}) passou de um herói no máximo ({$maxedHero}).");
        }
    }

    public function test_power_do_equipamento_cresce_a_cada_tier(): void
    {
        foreach (['gaius', 'ancelot', 'mystic'] as $set) {
            $previous = 0.0;
            foreach (Equipment::QUALITIES as $tier) {
                $power = $this->calc->mythicEquipmentContribution($this->fullSet($set, $tier), $this->hero);

                $this->assertGreaterThan($previous, $power, "Set {$set}: {$tier} não supera o tier anterior.");
                $previous = $power;
            }
        }
    }
}
