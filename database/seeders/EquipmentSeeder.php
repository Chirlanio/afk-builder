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
 * - Power scaling: +10% por upgrade level, max 5 levels (Mítico/T1/T2) ou 0 (T3/T4)
 *
 * Cada item base (Mítico) gera 4 versões adicionais (T1-T4) com stats escalados
 * pelos TIER_MULTIPLIERS baseados nos snapshots do Bastão de Magia (única peça
 * com dados completos pra todos os tiers). T3/T4 ganham `set_bonuses` adicionais.
 */
class EquipmentSeeder extends Seeder
{
    /**
     * Multipliers extraídos do Bastão de Magia do Místico (T0..T4):
     *   ATK: 1500 → 3500 → 7500 → 13500 → 37000  ⇒  ×2.33, ×5.00, ×9.00, ×24.67
     *   CRIT: 24% → 24% → 24% → 43.2% → 43.2%    ⇒  ×1.0, ×1.0, ×1.8, ×1.8
     *   RM: 12% → 13% → 14% → 25.2% → 25.2%      ⇒  +1, +2, ×2.1, ×2.1 (relativo)
     *   Power: 7866 → 15500 → 30490 → 3091410 → 4216020 (T3+ inclui set_bonuses)
     *
     * Aplicamos a mesma escala em todas as peças (premissa: stats escalam de
     * forma homogênea entre slots no mesmo tier).
     */
    private const FLAT_MULTIPLIERS = [
        'mythic' => 1.0,
        'mythic_t1' => 2.33,
        'mythic_t2' => 5.0,
        'mythic_t3' => 9.0,
        'mythic_t4' => 24.67,
    ];

    private const CRIT_MULTIPLIERS = [
        'mythic' => 1.0,
        'mythic_t1' => 1.0,
        'mythic_t2' => 1.0,
        'mythic_t3' => 1.8,
        'mythic_t4' => 1.8,
    ];

    /**
     * RM/RF têm comportamento diferente do bastão: +1 por tier até T2, salto 2.1x em T3/T4.
     * Aplicado em qualquer stat _pct de resistência.
     */
    private const RESISTANCE_MULTIPLIERS = [
        'mythic' => 1.0,
        'mythic_t1' => 1.08, // 12 → 13 ≈ +8%
        'mythic_t2' => 1.17, // 12 → 14 ≈ +17%
        'mythic_t3' => 2.10, // 12 → 25.2
        'mythic_t4' => 2.10,
    ];

    /**
     * Power rating escala mais agressivo pra T3/T4 (inclui ganhos de set_bonuses).
     * Mantém alinhamento visual com o Bastão (cuja escala T0→T4 é ~536x).
     */
    private const POWER_MULTIPLIERS = [
        'mythic' => 1.0,
        'mythic_t1' => 1.97,
        'mythic_t2' => 3.88,
        'mythic_t3' => 393.0,
        'mythic_t4' => 535.0,
    ];

    /**
     * Set bonuses base (do Bastão T3). Peças não-Bastão recebem ~50% (premissa:
     * arma é a peça mais forte do set, demais slots dão bônus menores).
     */
    private const T3_SET_BONUSES_WEAPON = [
        'hp_pct' => 0.7,
        'hp_flat' => 1392830,
        'attack_pct' => 4.0,
        'attack_flat' => 582446,
        'defense_pct' => 1.9,
        'defense_flat' => 59674,
        'physical_resistance_pct' => 1.6,
    ];

    private const T4_SET_BONUSES_WEAPON = [
        'hp_pct' => 0.8,
        'hp_flat' => 1591806,
        'attack_pct' => 4.3,
        'attack_flat' => 626129,
        'defense_pct' => 2.0,
        'defense_flat' => 62815,
        'physical_resistance_pct' => 1.6,
    ];

    /** Slot weight: peças não-arma valem 50% do set bonus da arma. */
    private const NON_WEAPON_BONUS_WEIGHT = 0.5;

    public function run(): void
    {
        $factions = Faction::pluck('id', 'code');
        $baseItems = $this->baseDefinitions($factions);

        // Expande cada item base nos 5 tiers (Mítico → T4)
        $items = [];
        foreach ($baseItems as $base) {
            foreach (Equipment::QUALITIES as $tier) {
                $items[] = $this->buildTierItem($base, $tier);
            }
        }

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
     * Constrói um item específico no tier solicitado a partir do base (Mítico).
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function buildTierItem(array $base, string $tier): array
    {
        $isBase = $tier === Equipment::QUALITY_MYTHIC;
        $tierSuffix = $isBase ? '' : '-'.str_replace('mythic_', '', $tier);

        $stats = $this->scaleStats($base['stats'], $tier);
        $powerRating = (int) round($base['power_rating'] * (self::POWER_MULTIPLIERS[$tier] ?? 1.0));

        $setBonuses = null;
        if ($tier === Equipment::QUALITY_MYTHIC_T3 || $tier === Equipment::QUALITY_MYTHIC_T4) {
            $setBonuses = $this->buildSetBonuses($base['slot'], $tier);
        }

        $hasUpgrades = ! in_array($tier, [Equipment::QUALITY_MYTHIC_T3, Equipment::QUALITY_MYTHIC_T4], true);

        return [
            'slug' => $base['slug'].$tierSuffix,
            'name' => $base['name'].($isBase ? '' : ' '.strtoupper(str_replace('mythic_', '', $tier))),
            'name_pt' => $base['name_pt'].($isBase ? '' : ' '.strtoupper(str_replace('mythic_', '', $tier))),
            'set_code' => $base['set_code'],
            'slot' => $base['slot'],
            'material' => $base['material'],
            'hero_type' => $base['hero_type'],
            'faction_id' => $base['faction_id'],
            'quality_tier' => $tier,
            'power_rating' => $powerRating,
            'stats' => $stats,
            'set_bonuses' => $setBonuses,
            'max_upgrade_level' => $hasUpgrades ? 5 : 0,
            'upgrade_percent_per_level' => $hasUpgrades ? 10 : 0,
            'is_active' => true,
        ];
    }

    /**
     * Escala cada stat conforme seu "tipo" (flat, crit, resistance, etc.).
     *
     * @param  array<string, int|float>  $baseStats
     * @return array<string, int|float>
     */
    private function scaleStats(array $baseStats, string $tier): array
    {
        $scaled = [];
        foreach ($baseStats as $key => $value) {
            $multiplier = match ($key) {
                'crit_rate_pct' => self::CRIT_MULTIPLIERS[$tier] ?? 1.0,
                'physical_resistance_pct', 'magic_resistance_pct' => self::RESISTANCE_MULTIPLIERS[$tier] ?? 1.0,
                default => self::FLAT_MULTIPLIERS[$tier] ?? 1.0,
            };

            $isPct = str_ends_with($key, '_pct');
            $scaled[$key] = $isPct
                ? round((float) $value * $multiplier, 2)
                : (int) round((float) $value * $multiplier);
        }

        return $scaled;
    }

    /**
     * @return array<string, float|int>
     */
    private function buildSetBonuses(string $slot, string $tier): array
    {
        $source = $tier === Equipment::QUALITY_MYTHIC_T3
            ? self::T3_SET_BONUSES_WEAPON
            : self::T4_SET_BONUSES_WEAPON;

        if ($slot === Equipment::SLOT_WEAPON) {
            return $source; // weapon mantém valores originais
        }

        // Não-weapon recebe 50% dos valores
        $bonuses = [];
        foreach ($source as $key => $value) {
            $isPct = str_ends_with($key, '_pct');
            $bonuses[$key] = $isPct
                ? round((float) $value * self::NON_WEAPON_BONUS_WEIGHT, 2)
                : (int) round((float) $value * self::NON_WEAPON_BONUS_WEIGHT);
        }

        return $bonuses;
    }

    /**
     * Itens base (apenas Mítico). Cada um vira 5 versões via buildTierItem.
     *
     * @param  \Illuminate\Support\Collection<string, int>  $factions
     * @return array<int, array<string, mixed>>
     */
    private function baseDefinitions($factions): array
    {
        return [
            // === SET GAIUS — Heroes baseados em Força (Plate Armor + Heavy Weapon) ===
            [
                'slug' => 'gaius-grevas-lightbearer',
                'name' => 'Gaius Greaves (Lightbearer)',
                'name_pt' => 'Grevas de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_PLATE,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'faction_id' => $factions['lightbearer'],
                'power_rating' => 7768,
                'stats' => [
                    'hp' => 36500,
                    'accuracy' => 60,
                    'haste' => 5,
                    'physical_resistance_pct' => 5,
                ],
            ],
            [
                'slug' => 'gaius-grevas-celestial',
                'name' => 'Gaius Greaves (Celestial)',
                'name_pt' => 'Grevas de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_PLATE,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'faction_id' => $factions['celestial'],
                'power_rating' => 7768,
                'stats' => [
                    'hp' => 36500,
                    'accuracy' => 60,
                    'haste' => 5,
                    'physical_resistance_pct' => 5,
                ],
            ],
            [
                'slug' => 'gaius-couraca-wilder',
                'name' => 'Gaius Cuirass (Wilder)',
                'name_pt' => 'Couraça de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_CHEST,
                'material' => Equipment::MATERIAL_PLATE,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'faction_id' => $factions['wilder'],
                'power_rating' => 7762,
                'stats' => [
                    'hp' => 20000,
                    'defense' => 720,
                    'physical_resistance_pct' => 10,
                ],
            ],
            [
                'slug' => 'gaius-elmo-graveborn',
                'name' => 'Gaius Helm (Graveborn)',
                'name_pt' => 'Elmo de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_HELMET,
                'material' => Equipment::MATERIAL_PLATE,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'faction_id' => $factions['graveborn'],
                'power_rating' => 7737,
                'stats' => [
                    'hp' => 18500,
                    'attack' => 950,
                    'crit_rate_pct' => 5,
                    'dodge' => 40,
                ],
            ],
            [
                'slug' => 'gaius-mangual-wilder',
                'name' => 'Gaius Flail (Wilder)',
                'name_pt' => 'Mangual de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_WEAPON,
                'material' => Equipment::MATERIAL_HEAVY_WEAPON,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'faction_id' => $factions['wilder'],
                'power_rating' => 7715,
                'stats' => [
                    'attack' => 1650,
                    'defense' => 150,
                    'accuracy' => 100,
                    'physical_resistance_pct' => 12,
                ],
            ],

            // === SET ANCELOT — Heroes baseados em Agilidade (Leather + Light Weapon) ===
            [
                'slug' => 'ancelot-perseveranca-graveborn',
                'name' => 'Ancelot Perseverance (Graveborn)',
                'name_pt' => 'Perseverança de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_LEATHER,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'faction_id' => $factions['graveborn'],
                'power_rating' => 7723,
                'stats' => [
                    'hp' => 26000,
                    'attack' => 500,
                    'crit_rate_pct' => 4,
                    'dodge' => 100,
                ],
            ],
            [
                'slug' => 'ancelot-perseveranca-celestial',
                'name' => 'Ancelot Perseverance (Celestial)',
                'name_pt' => 'Perseverança de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_LEATHER,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'faction_id' => $factions['celestial'],
                'power_rating' => 7723,
                'stats' => [
                    'hp' => 26000,
                    'attack' => 500,
                    'crit_rate_pct' => 4,
                    'dodge' => 100,
                ],
            ],
            [
                'slug' => 'ancelot-honra-lightbearer',
                'name' => 'Ancelot Honor (Lightbearer)',
                'name_pt' => 'Honra de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_CHEST,
                'material' => Equipment::MATERIAL_LEATHER,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'faction_id' => $factions['lightbearer'],
                'power_rating' => 7750,
                'stats' => [
                    'hp' => 18000,
                    'defense' => 700,
                    'crit_rate_pct' => 10,
                    'dodge' => 50,
                ],
            ],
            [
                'slug' => 'ancelot-refugio-graveborn',
                'name' => 'Ancelot Refuge (Graveborn)',
                'name_pt' => 'Refúgio de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_HELMET,
                'material' => Equipment::MATERIAL_LEATHER,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'faction_id' => $factions['graveborn'],
                'power_rating' => 7744,
                'stats' => [
                    'hp' => 11000,
                    'attack' => 1300,
                    'defense' => 110,
                    'accuracy' => 60,
                ],
            ],
            [
                'slug' => 'ancelot-lamina-hypogean',
                'name' => 'Ancelot Blade (Hypogean)',
                'name_pt' => 'Lâmina de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_WEAPON,
                'material' => Equipment::MATERIAL_LIGHT_WEAPON,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'faction_id' => $factions['hypogean'],
                'power_rating' => 7715,
                'stats' => [
                    'attack' => 1800,
                    'dodge' => 85,
                    'accuracy' => 200,
                ],
            ],

            // === SET MÍSTICO — Heroes baseados em Inteligência (Padded + Magic Staff) ===
            [
                'slug' => 'mystic-botas-celestial',
                'name' => 'Mystic Boots (Celestial)',
                'name_pt' => 'Botas Místicas de Mágica',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_PADDED,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
                'faction_id' => $factions['celestial'],
                'power_rating' => 7735,
                'stats' => [
                    'hp' => 32000,
                    'attack' => 250,
                    'haste' => 10,
                    'dodge' => 100,
                ],
            ],
            [
                'slug' => 'mystic-casaco-celestial',
                'name' => 'Mystic Coat (Celestial)',
                'name_pt' => 'Casaco Místico de Mágica',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_CHEST,
                'material' => Equipment::MATERIAL_PADDED,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
                'faction_id' => $factions['celestial'],
                'power_rating' => 7742,
                'stats' => [
                    'hp' => 13000,
                    'attack' => 460,
                    'defense' => 650,
                    'magic_resistance_pct' => 10,
                ],
            ],
            [
                'slug' => 'mystic-chapeu-celestial',
                'name' => 'Mystic Hat (Celestial)',
                'name_pt' => 'Chapéu de Mística da Mágica',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_HELMET,
                'material' => Equipment::MATERIAL_PADDED,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
                'faction_id' => $factions['celestial'],
                'power_rating' => 7792,
                'stats' => [
                    'hp' => 14000,
                    'attack' => 1100,
                    'defense' => 120,
                    'crit_rate_pct' => 5,
                ],
            ],
            [
                'slug' => 'mystic-bastao-hypogean',
                'name' => 'Mystic Magic Staff (Hypogean)',
                'name_pt' => 'Bastão de Magia do Místico',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_WEAPON,
                'material' => Equipment::MATERIAL_MAGIC_STAFF,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
                'faction_id' => $factions['hypogean'],
                'power_rating' => 7866,
                'stats' => [
                    'attack' => 1500,
                    'crit_rate_pct' => 24,
                    'magic_resistance_pct' => 12,
                ],
            ],
        ];
    }
}
