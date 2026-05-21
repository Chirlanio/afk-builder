<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Faction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds equipamentos míticos do AFK Arena baseados nos snapshots PT-BR (2026-05)
 * compartilhados pelo player. 3 sets (Gaius/Ancelot/Místico), 4 slots cada
 * (botas/peitoral/elmo/arma), com variações por facção e quality tier.
 *
 * Convenções:
 * - Stats com sufixo `_pct` são percentuais (ex.: physical_resistance_pct = 5 → 5%)
 * - Stats sem sufixo são flat (ex.: hp = 36500)
 * - Power scaling: +10% por upgrade level, max 5 levels (T0/T1/T2) ou 0 (T3/T4)
 */
class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $factions = Faction::pluck('id', 'code');

        $items = $this->definitions($factions);

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Equipment::updateOrCreate(
                    ['slug' => $item['slug']],
                    $item,
                );
            }
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $factions
     * @return array<int, array<string, mixed>>
     */
    private function definitions($factions): array
    {
        $items = [];

        // === SET GAIUS — Heroes baseados em Força (Plate Armor + Heavy Weapon) ===

        // Grevas (Botas), faction Lightbearer
        $items[] = [
            'slug' => 'gaius-grevas-lightbearer',
            'name' => 'Gaius Greaves (Lightbearer)',
            'name_pt' => 'Grevas de Gaius',
            'set_code' => 'gaius',
            'slot' => Equipment::SLOT_BOOTS,
            'material' => Equipment::MATERIAL_PLATE,
            'hero_type' => Equipment::HERO_TYPE_STRENGTH,
            'faction_id' => $factions['lightbearer'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7768,
            'stats' => [
                'hp' => 36500,
                'accuracy' => 60,
                'haste' => 5,
                'physical_resistance_pct' => 5,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Grevas, faction Celestial
        $items[] = [
            'slug' => 'gaius-grevas-celestial',
            'name' => 'Gaius Greaves (Celestial)',
            'name_pt' => 'Grevas de Gaius',
            'set_code' => 'gaius',
            'slot' => Equipment::SLOT_BOOTS,
            'material' => Equipment::MATERIAL_PLATE,
            'hero_type' => Equipment::HERO_TYPE_STRENGTH,
            'faction_id' => $factions['celestial'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7768,
            'stats' => [
                'hp' => 36500,
                'accuracy' => 60,
                'haste' => 5,
                'physical_resistance_pct' => 5,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Couraça (Peitoral), faction Wilder
        $items[] = [
            'slug' => 'gaius-couraca-wilder',
            'name' => 'Gaius Cuirass (Wilder)',
            'name_pt' => 'Couraça de Gaius',
            'set_code' => 'gaius',
            'slot' => Equipment::SLOT_CHEST,
            'material' => Equipment::MATERIAL_PLATE,
            'hero_type' => Equipment::HERO_TYPE_STRENGTH,
            'faction_id' => $factions['wilder'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7762,
            'stats' => [
                'hp' => 20000,
                'defense' => 720,
                'physical_resistance_pct' => 10,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Elmo, faction Graveborn
        $items[] = [
            'slug' => 'gaius-elmo-graveborn',
            'name' => 'Gaius Helm (Graveborn)',
            'name_pt' => 'Elmo de Gaius',
            'set_code' => 'gaius',
            'slot' => Equipment::SLOT_HELMET,
            'material' => Equipment::MATERIAL_PLATE,
            'hero_type' => Equipment::HERO_TYPE_STRENGTH,
            'faction_id' => $factions['graveborn'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7737,
            'stats' => [
                'hp' => 18500,
                'attack' => 950,
                'crit_rate_pct' => 5,
                'dodge' => 40,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Mangual (Arma pesada), faction Wilder
        $items[] = [
            'slug' => 'gaius-mangual-wilder',
            'name' => 'Gaius Flail (Wilder)',
            'name_pt' => 'Mangual de Gaius',
            'set_code' => 'gaius',
            'slot' => Equipment::SLOT_WEAPON,
            'material' => Equipment::MATERIAL_HEAVY_WEAPON,
            'hero_type' => Equipment::HERO_TYPE_STRENGTH,
            'faction_id' => $factions['wilder'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7715,
            'stats' => [
                'attack' => 1650,
                'defense' => 150,
                'accuracy' => 100,
                'physical_resistance_pct' => 12,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // === SET ANCELOT — Heroes baseados em Agilidade (Leather + Light Weapon) ===

        // Perseverança (Botas), faction Graveborn
        $items[] = [
            'slug' => 'ancelot-perseveranca-graveborn',
            'name' => 'Ancelot Perseverance (Graveborn)',
            'name_pt' => 'Perseverança de Ancelot',
            'set_code' => 'ancelot',
            'slot' => Equipment::SLOT_BOOTS,
            'material' => Equipment::MATERIAL_LEATHER,
            'hero_type' => Equipment::HERO_TYPE_AGILITY,
            'faction_id' => $factions['graveborn'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7723,
            'stats' => [
                'hp' => 26000,
                'attack' => 500,
                'crit_rate_pct' => 4,
                'dodge' => 100,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Perseverança, faction Celestial
        $items[] = [
            'slug' => 'ancelot-perseveranca-celestial',
            'name' => 'Ancelot Perseverance (Celestial)',
            'name_pt' => 'Perseverança de Ancelot',
            'set_code' => 'ancelot',
            'slot' => Equipment::SLOT_BOOTS,
            'material' => Equipment::MATERIAL_LEATHER,
            'hero_type' => Equipment::HERO_TYPE_AGILITY,
            'faction_id' => $factions['celestial'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7723,
            'stats' => [
                'hp' => 26000,
                'attack' => 500,
                'crit_rate_pct' => 4,
                'dodge' => 100,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Honra (Peitoral), faction Lightbearer
        $items[] = [
            'slug' => 'ancelot-honra-lightbearer',
            'name' => 'Ancelot Honor (Lightbearer)',
            'name_pt' => 'Honra de Ancelot',
            'set_code' => 'ancelot',
            'slot' => Equipment::SLOT_CHEST,
            'material' => Equipment::MATERIAL_LEATHER,
            'hero_type' => Equipment::HERO_TYPE_AGILITY,
            'faction_id' => $factions['lightbearer'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7750,
            'stats' => [
                'hp' => 18000,
                'defense' => 700,
                'crit_rate_pct' => 10,
                'dodge' => 50,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Refúgio (Elmo), faction Graveborn
        $items[] = [
            'slug' => 'ancelot-refugio-graveborn',
            'name' => 'Ancelot Refuge (Graveborn)',
            'name_pt' => 'Refúgio de Ancelot',
            'set_code' => 'ancelot',
            'slot' => Equipment::SLOT_HELMET,
            'material' => Equipment::MATERIAL_LEATHER,
            'hero_type' => Equipment::HERO_TYPE_AGILITY,
            'faction_id' => $factions['graveborn'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7744,
            'stats' => [
                'hp' => 11000,
                'attack' => 1300,
                'defense' => 110,
                'accuracy' => 60,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Lâmina (Arma leve), faction Hypogean
        $items[] = [
            'slug' => 'ancelot-lamina-hypogean',
            'name' => 'Ancelot Blade (Hypogean)',
            'name_pt' => 'Lâmina de Ancelot',
            'set_code' => 'ancelot',
            'slot' => Equipment::SLOT_WEAPON,
            'material' => Equipment::MATERIAL_LIGHT_WEAPON,
            'hero_type' => Equipment::HERO_TYPE_AGILITY,
            'faction_id' => $factions['hypogean'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7715,
            'stats' => [
                'attack' => 1800,
                'dodge' => 85,
                'accuracy' => 200,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // === SET MÍSTICO — Heroes baseados em Inteligência (Padded + Magic Staff) ===

        // Botas Místicas, faction Celestial
        $items[] = [
            'slug' => 'mystic-botas-celestial',
            'name' => 'Mystic Boots (Celestial)',
            'name_pt' => 'Botas Místicas de Mágica',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_BOOTS,
            'material' => Equipment::MATERIAL_PADDED,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['celestial'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7735,
            'stats' => [
                'hp' => 32000,
                'attack' => 250,
                'haste' => 10,
                'dodge' => 100,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Casaco Místico (Peitoral), faction Celestial
        $items[] = [
            'slug' => 'mystic-casaco-celestial',
            'name' => 'Mystic Coat (Celestial)',
            'name_pt' => 'Casaco Místico de Mágica',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_CHEST,
            'material' => Equipment::MATERIAL_PADDED,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['celestial'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7742,
            'stats' => [
                'hp' => 13000,
                'attack' => 460,
                'defense' => 650,
                'magic_resistance_pct' => 10,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Chapéu de Mística (Elmo), faction Celestial
        $items[] = [
            'slug' => 'mystic-chapeu-celestial',
            'name' => 'Mystic Hat (Celestial)',
            'name_pt' => 'Chapéu de Mística da Mágica',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_HELMET,
            'material' => Equipment::MATERIAL_PADDED,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['celestial'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7792,
            'stats' => [
                'hp' => 14000,
                'attack' => 1100,
                'defense' => 120,
                'crit_rate_pct' => 5,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Bastão de Magia (Arma — Magic Staff) — base mythic, faction Hypogean
        $items[] = [
            'slug' => 'mystic-bastao-hypogean',
            'name' => 'Mystic Magic Staff (Hypogean)',
            'name_pt' => 'Bastão de Magia do Místico',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_WEAPON,
            'material' => Equipment::MATERIAL_MAGIC_STAFF,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['hypogean'],
            'quality_tier' => Equipment::QUALITY_MYTHIC,
            'power_rating' => 7866,
            'stats' => [
                'attack' => 1500,
                'crit_rate_pct' => 24,
                'magic_resistance_pct' => 12,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Bastão T1
        $items[] = [
            'slug' => 'mystic-bastao-hypogean-t1',
            'name' => 'Mystic Magic Staff T1 (Hypogean)',
            'name_pt' => 'Bastão de Magia do Místico T1',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_WEAPON,
            'material' => Equipment::MATERIAL_MAGIC_STAFF,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['hypogean'],
            'quality_tier' => Equipment::QUALITY_MYTHIC_T1,
            'power_rating' => 15500,
            'stats' => [
                'attack' => 3500,
                'crit_rate_pct' => 24,
                'magic_resistance_pct' => 13,
                'dodge' => 50,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Bastão T2
        $items[] = [
            'slug' => 'mystic-bastao-hypogean-t2',
            'name' => 'Mystic Magic Staff T2 (Hypogean)',
            'name_pt' => 'Bastão de Magia do Místico T2',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_WEAPON,
            'material' => Equipment::MATERIAL_MAGIC_STAFF,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['hypogean'],
            'quality_tier' => Equipment::QUALITY_MYTHIC_T2,
            'power_rating' => 30490,
            'stats' => [
                'attack' => 7500,
                'crit_rate_pct' => 24,
                'magic_resistance_pct' => 14,
                'dodge' => 90,
            ],
            'set_bonuses' => null,
            'max_upgrade_level' => 5,
            'upgrade_percent_per_level' => 10,
            'is_active' => true,
        ];

        // Bastão T3 — sem upgrades, com set bonuses
        $items[] = [
            'slug' => 'mystic-bastao-hypogean-t3',
            'name' => 'Mystic Magic Staff T3 (Hypogean)',
            'name_pt' => 'Bastão de Magia do Místico T3',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_WEAPON,
            'material' => Equipment::MATERIAL_MAGIC_STAFF,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['hypogean'],
            'quality_tier' => Equipment::QUALITY_MYTHIC_T3,
            'power_rating' => 3091410, // 3091.41k
            'stats' => [
                'attack' => 13500,
                'crit_rate_pct' => 43.2,
                'magic_resistance_pct' => 25.2,
                'dodge' => 162,
            ],
            'set_bonuses' => [
                'hp_pct' => 0.7,
                'hp_flat' => 1392830,
                'attack_pct' => 4,
                'attack_flat' => 582446,
                'defense_pct' => 1.9,
                'defense_flat' => 59674,
                'physical_resistance_pct' => 1.6,
            ],
            'max_upgrade_level' => 0,
            'upgrade_percent_per_level' => 0,
            'is_active' => true,
        ];

        // Bastão T4 — sem upgrades, set bonuses maiores
        $items[] = [
            'slug' => 'mystic-bastao-hypogean-t4',
            'name' => 'Mystic Magic Staff T4 (Hypogean)',
            'name_pt' => 'Bastão de Magia do Místico T4',
            'set_code' => 'mystic',
            'slot' => Equipment::SLOT_WEAPON,
            'material' => Equipment::MATERIAL_MAGIC_STAFF,
            'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
            'faction_id' => $factions['hypogean'],
            'quality_tier' => Equipment::QUALITY_MYTHIC_T4,
            'power_rating' => 4216020, // 4216.02k
            'stats' => [
                'attack' => 37000,
                'crit_rate_pct' => 43.2,
                'magic_resistance_pct' => 25.2,
                'dodge' => 280,
            ],
            'set_bonuses' => [
                'hp_pct' => 0.8,
                'hp_flat' => 1591806,
                'attack_pct' => 4.3,
                'attack_flat' => 626129,
                'defense_pct' => 2,
                'defense_flat' => 62815,
                'physical_resistance_pct' => 1.6,
            ],
            'max_upgrade_level' => 0,
            'upgrade_percent_per_level' => 0,
            'is_active' => true,
        ];

        return $items;
    }
}
