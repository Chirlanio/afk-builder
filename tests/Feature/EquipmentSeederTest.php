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

        // 12 templates × 8 factions × 5 tiers = 480 items
        // Templates: 4 Gaius (strength) + 4 Ancelot (agility) + 4 Místico (intelligence)
        $this->assertSame(480, Equipment::count());
        $this->assertSame(96, Equipment::where('quality_tier', 'mythic')->count());
        $this->assertSame(96, Equipment::where('quality_tier', 'mythic_t1')->count());
        $this->assertSame(96, Equipment::where('quality_tier', 'mythic_t4')->count());
    }

    public function test_grevas_de_gaius_existe_em_todas_factions(): void
    {
        $this->seed(EquipmentSeeder::class);

        $grevas = Equipment::where('set_code', 'gaius')
            ->where('slot', Equipment::SLOT_BOOTS)
            ->where('quality_tier', 'mythic')
            ->get();

        // 8 factions × 1 item base = 8 items
        $this->assertCount(8, $grevas);
        // Stats idênticos entre factions
        $this->assertTrue($grevas->every(fn ($g) => $g->power_rating === 7768));
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

        // Cada combinação (template × faction) deve ter 5 tiers
        // Sample uns slugs específicos pra validar a estrutura
        $samples = [
            'gaius-grevas-lightbearer', 'gaius-grevas-mauler', 'gaius-grevas-dimensional',
            'mystic-bastao-draconis', 'ancelot-lamina-celestial',
        ];

        foreach ($samples as $base) {
            $this->assertTrue(Equipment::where('slug', $base)->exists(), "Mítico não existe: $base");
            foreach (['t1', 't2', 't3', 't4'] as $tier) {
                $this->assertTrue(
                    Equipment::where('slug', "$base-$tier")->exists(),
                    "Tier $tier não existe: $base-$tier",
                );
            }
        }
    }

    public function test_cada_template_existe_em_todas_8_factions(): void
    {
        $this->seed(EquipmentSeeder::class);

        // 12 templates × 8 factions × 5 tiers = 480
        $templates = ['gaius-grevas', 'gaius-couraca', 'gaius-elmo', 'gaius-mangual',
                      'ancelot-perseveranca', 'ancelot-honra', 'ancelot-refugio', 'ancelot-lamina',
                      'mystic-botas', 'mystic-casaco', 'mystic-chapeu', 'mystic-bastao'];

        foreach ($templates as $template) {
            $count = Equipment::where('slug', 'LIKE', "{$template}-%")->where('quality_tier', 'mythic')->count();
            $this->assertSame(8, $count, "Template $template deve ter 8 facções (Mítico)");
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
