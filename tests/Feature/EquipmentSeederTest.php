<?php

namespace Tests\Feature;

use App\Models\Equipment;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\FactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FactionSeeder::class);
    }

    public function test_seeder_cria_todos_os_equipamentos_principais(): void
    {
        $this->seed(EquipmentSeeder::class);

        // 14 items base × 5 tiers (Mítico, T1, T2, T3, T4) = 70 items
        // Base: 5 Gaius + 5 Ancelot + 4 Místico
        $this->assertSame(70, Equipment::count());
        $this->assertSame(14, Equipment::where('quality_tier', 'mythic')->count());
        $this->assertSame(14, Equipment::where('quality_tier', 'mythic_t1')->count());
        $this->assertSame(14, Equipment::where('quality_tier', 'mythic_t4')->count());
    }

    public function test_grevas_de_gaius_existe_em_duas_factions(): void
    {
        $this->seed(EquipmentSeeder::class);

        $grevas = Equipment::where('set_code', 'gaius')
            ->where('slot', Equipment::SLOT_BOOTS)
            ->where('quality_tier', 'mythic')
            ->get();

        $this->assertCount(2, $grevas);
        $this->assertSame(7768, $grevas[0]->power_rating);
        $this->assertSame(7768, $grevas[1]->power_rating);
    }

    public function test_stats_json_decodifica_com_sufixos_corretos(): void
    {
        $this->seed(EquipmentSeeder::class);

        $grevas = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();

        $this->assertSame(36500, $grevas->stats['hp']);
        $this->assertSame(60, $grevas->stats['accuracy']);
        $this->assertSame(5, $grevas->stats['haste']);
        $this->assertSame(5, $grevas->stats['physical_resistance_pct']);
    }

    public function test_t3_e_t4_tem_set_bonuses_e_sem_upgrades(): void
    {
        $this->seed(EquipmentSeeder::class);

        $t3 = Equipment::where('slug', 'mystic-bastao-hypogean-t3')->firstOrFail();
        $t4 = Equipment::where('slug', 'mystic-bastao-hypogean-t4')->firstOrFail();

        $this->assertSame(0, $t3->max_upgrade_level);
        $this->assertSame(0, $t4->max_upgrade_level);

        $this->assertNotNull($t3->set_bonuses);
        $this->assertSame(0.7, $t3->set_bonuses['hp_pct']);
        $this->assertSame(1392830, $t3->set_bonuses['hp_flat']);

        $this->assertNotNull($t4->set_bonuses);
        $this->assertSame(1591806, $t4->set_bonuses['hp_flat']);
    }

    public function test_tier_anterior_T2_tem_upgrades_mas_sem_set_bonus(): void
    {
        $this->seed(EquipmentSeeder::class);

        $t2 = Equipment::where('slug', 'mystic-bastao-hypogean-t2')->firstOrFail();

        $this->assertSame(5, $t2->max_upgrade_level);
        $this->assertSame(10, $t2->upgrade_percent_per_level);
        $this->assertNull($t2->set_bonuses);
    }

    public function test_power_at_level_escala_em_10_porcento_por_nivel(): void
    {
        $this->seed(EquipmentSeeder::class);

        $grevas = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();

        $this->assertSame(7768, $grevas->powerAtLevel(0));
        $this->assertSame(8545, $grevas->powerAtLevel(1));  // 7768 * 1.10
        $this->assertSame(11652, $grevas->powerAtLevel(5)); // 7768 * 1.50
        // Clamp acima do max:
        $this->assertSame(11652, $grevas->powerAtLevel(99));
    }

    public function test_stats_at_level_escala_com_o_upgrade(): void
    {
        $this->seed(EquipmentSeeder::class);

        $grevas = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();

        $l0 = $grevas->statsAtLevel(0);
        $this->assertSame(36500.0, $l0['hp']);

        $l5 = $grevas->statsAtLevel(5);
        $this->assertSame(54750.0, $l5['hp']);   // 36500 * 1.50
        $this->assertSame(7.5, $l5['physical_resistance_pct']); // 5 * 1.50
    }

    public function test_t3_sem_upgrades_nao_escala_stats(): void
    {
        $this->seed(EquipmentSeeder::class);

        $t3 = Equipment::where('slug', 'mystic-bastao-hypogean-t3')->firstOrFail();
        $l5 = $t3->statsAtLevel(5); // tenta upgrade — deve ser ignorado

        $this->assertSame(13500.0, $l5['attack']); // mesmo valor que L0
    }

    public function test_todos_os_equipamentos_tem_5_tiers(): void
    {
        $this->seed(EquipmentSeeder::class);

        // Cada base slug (gaius-grevas-lightbearer, gaius-couraca-wilder, etc) deve
        // ter exatamente 5 versões (Mítico, T1, T2, T3, T4) na DB.
        $baseSlugs = [
            'gaius-grevas-lightbearer', 'gaius-grevas-celestial',
            'gaius-couraca-wilder', 'gaius-elmo-graveborn', 'gaius-mangual-wilder',
            'ancelot-perseveranca-graveborn', 'ancelot-perseveranca-celestial',
            'ancelot-honra-lightbearer', 'ancelot-refugio-graveborn', 'ancelot-lamina-hypogean',
            'mystic-botas-celestial', 'mystic-casaco-celestial',
            'mystic-chapeu-celestial', 'mystic-bastao-hypogean',
        ];

        foreach ($baseSlugs as $base) {
            $this->assertTrue(Equipment::where('slug', $base)->exists(), "Mítico não existe: $base");
            foreach (['t1', 't2', 't3', 't4'] as $tier) {
                $this->assertTrue(
                    Equipment::where('slug', "$base-$tier")->exists(),
                    "Tier $tier não existe: $base-$tier",
                );
            }
        }
    }

    public function test_t3_e_t4_dos_outros_slots_tambem_tem_set_bonuses_menores(): void
    {
        $this->seed(EquipmentSeeder::class);

        // Bastão T3 (arma) tem set_bonuses originais
        $bastao = Equipment::where('slug', 'mystic-bastao-hypogean-t3')->firstOrFail();
        $this->assertSame(1392830, $bastao->set_bonuses['hp_flat']);

        // Elmo T3 (não-arma) tem 50% dos valores
        $elmo = Equipment::where('slug', 'gaius-elmo-graveborn-t3')->firstOrFail();
        $this->assertSame(696415, $elmo->set_bonuses['hp_flat']); // 1392830 / 2
        $this->assertSame(0.35, $elmo->set_bonuses['hp_pct']); // 0.7 / 2
    }

    public function test_stats_escalam_consistentemente_entre_tiers(): void
    {
        $this->seed(EquipmentSeeder::class);

        $mythic = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();
        $t1 = Equipment::where('slug', 'gaius-grevas-lightbearer-t1')->firstOrFail();
        $t4 = Equipment::where('slug', 'gaius-grevas-lightbearer-t4')->firstOrFail();

        // HP flat escala pelo FLAT_MULTIPLIER (×2.33 T1, ×24.67 T4)
        $this->assertSame(36500, $mythic->stats['hp']);
        $this->assertSame(85045, $t1->stats['hp']);  // round(36500 * 2.33)
        $this->assertSame(900455, $t4->stats['hp']); // round(36500 * 24.67)

        // physical_resistance_pct escala pelo RESISTANCE_MULTIPLIER (×1.08, ×2.10)
        $this->assertSame(5, $mythic->stats['physical_resistance_pct']);
        $this->assertSame(5.4, $t1->stats['physical_resistance_pct']);
        $this->assertSame(10.5, $t4->stats['physical_resistance_pct']);
    }

    public function test_apply_faction_bonus_aumenta_stats_em_30_porcento(): void
    {
        $this->seed(EquipmentSeeder::class);

        $grevas = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();
        $stats = $grevas->statsAtLevel(0);

        $boosted = $grevas->applyFactionBonus($stats, true);
        $this->assertSame(47450.0, $boosted['hp']);   // 36500 * 1.30
        $this->assertSame(78.0, $boosted['accuracy']); // 60 * 1.30

        $notBoosted = $grevas->applyFactionBonus($stats, false);
        $this->assertSame(36500.0, $notBoosted['hp']); // sem bônus
    }
}
