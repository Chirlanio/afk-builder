<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Faction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds equipamentos míticos do AFK Arena baseados nos snapshots PT-BR (2026-05)
 * compartilhados pelo player.
 *
 * Estrutura: 3 SETS (Gaius/Ancelot/Místico) × 4 SLOTS (boots/chest/helmet/weapon) =
 * 12 templates de item. Cada template gera 1 versão por FACÇÃO (8 factions) × 5
 * QUALITY TIERS (Mítico, T1, T2, T3, T4) = 480 items totais.
 *
 * Cada faction tem seu próprio "drop" do mesmo equipment — stats idênticos entre
 * factions, só o `faction_id` muda. O bônus de 30% é aplicado quando o hero
 * pertence à mesma faction do equipamento equipado.
 *
 * Convenções:
 * - Stats com sufixo `_pct` são percentuais (ex.: physical_resistance_pct = 5 → 5%)
 * - Stats sem sufixo são flat (ex.: hp = 36500)
 * - Power scaling: +10% por upgrade level, max 5 levels (Mítico/T1/T2) ou 0 (T3/T4)
 */
class EquipmentSeeder extends Seeder
{
    /** Todas as 8 factions do jogo. */
    private const ALL_FACTIONS = [
        'lightbearer', 'mauler', 'wilder', 'graveborn',
        'celestial', 'hypogean', 'dimensional', 'draconis',
    ];

    /**
     * Multipliers extraídos do Bastão de Magia do Místico (T0..T4):
     *   ATK: 1500 → 3500 → 7500 → 13500 → 37000
     *   CRIT: 24% → 24% → 24% → 43.2% → 43.2%
     *   RM: 12% → 13% → 14% → 25.2% → 25.2%
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

    private const RESISTANCE_MULTIPLIERS = [
        'mythic' => 1.0,
        'mythic_t1' => 1.08,
        'mythic_t2' => 1.17,
        'mythic_t3' => 2.10,
        'mythic_t4' => 2.10,
    ];

    private const POWER_MULTIPLIERS = [
        'mythic' => 1.0,
        'mythic_t1' => 1.97,
        'mythic_t2' => 3.88,
        'mythic_t3' => 393.0,
        'mythic_t4' => 535.0,
    ];

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
        $templates = $this->itemTemplates();

        $items = [];
        foreach ($templates as $template) {
            foreach (self::ALL_FACTIONS as $factionCode) {
                if (! isset($factions[$factionCode])) {
                    continue; // Faction não seedada — pula
                }
                foreach (Equipment::QUALITIES as $tier) {
                    $items[] = $this->buildItem($template, $factionCode, $factions[$factionCode], $tier);
                }
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
     * Constrói um item específico (template × faction × tier).
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function buildItem(array $template, string $factionCode, int $factionId, string $tier): array
    {
        $isBase = $tier === Equipment::QUALITY_MYTHIC;
        $tierSuffix = $isBase ? '' : '-'.str_replace('mythic_', '', $tier);
        $tierLabel = $isBase ? '' : ' '.strtoupper(str_replace('mythic_', '', $tier));

        $stats = $this->scaleStats($template['stats'], $tier);
        $powerRating = (int) round($template['power_rating'] * (self::POWER_MULTIPLIERS[$tier] ?? 1.0));

        $setBonuses = null;
        if ($tier === Equipment::QUALITY_MYTHIC_T3 || $tier === Equipment::QUALITY_MYTHIC_T4) {
            $setBonuses = $this->buildSetBonuses($template['slot'], $tier);
        }

        $hasUpgrades = ! in_array($tier, [Equipment::QUALITY_MYTHIC_T3, Equipment::QUALITY_MYTHIC_T4], true);

        return [
            'slug' => "{$template['slug_prefix']}-{$factionCode}{$tierSuffix}",
            'name' => $template['name'].$tierLabel,
            'name_pt' => $template['name_pt'].$tierLabel,
            'set_code' => $template['set_code'],
            'slot' => $template['slot'],
            'material' => $template['material'],
            'hero_type' => $template['hero_type'],
            'faction_id' => $factionId,
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
            return $source;
        }

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
     * 12 templates de item: 4 slots (boots/chest/helmet/weapon) × 3 sets (Gaius/
     * Ancelot/Místico). Stats baseados nos snapshots reais do PT-BR.
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemTemplates(): array
    {
        return [
            // === SET GAIUS — Heroes baseados em Força (Plate Armor + Heavy Weapon) ===
            [
                'slug_prefix' => 'gaius-grevas',
                'name' => 'Gaius Greaves',
                'name_pt' => 'Grevas de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_PLATE,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'power_rating' => 7768,
                'stats' => [
                    'hp' => 36500,
                    'accuracy' => 60,
                    'haste' => 5,
                    'physical_resistance_pct' => 5,
                ],
            ],
            [
                'slug_prefix' => 'gaius-couraca',
                'name' => 'Gaius Cuirass',
                'name_pt' => 'Couraça de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_CHEST,
                'material' => Equipment::MATERIAL_PLATE,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'power_rating' => 7762,
                'stats' => [
                    'hp' => 20000,
                    'defense' => 720,
                    'physical_resistance_pct' => 10,
                ],
            ],
            [
                'slug_prefix' => 'gaius-elmo',
                'name' => 'Gaius Helm',
                'name_pt' => 'Elmo de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_HELMET,
                'material' => Equipment::MATERIAL_PLATE,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
                'power_rating' => 7737,
                'stats' => [
                    'hp' => 18500,
                    'attack' => 950,
                    'crit_rate_pct' => 5,
                    'dodge' => 40,
                ],
            ],
            [
                'slug_prefix' => 'gaius-mangual',
                'name' => 'Gaius Flail',
                'name_pt' => 'Mangual de Gaius',
                'set_code' => 'gaius',
                'slot' => Equipment::SLOT_WEAPON,
                'material' => Equipment::MATERIAL_HEAVY_WEAPON,
                'hero_type' => Equipment::HERO_TYPE_STRENGTH,
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
                'slug_prefix' => 'ancelot-perseveranca',
                'name' => 'Ancelot Perseverance',
                'name_pt' => 'Perseverança de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_LEATHER,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'power_rating' => 7723,
                'stats' => [
                    'hp' => 26000,
                    'attack' => 500,
                    'crit_rate_pct' => 4,
                    'dodge' => 100,
                ],
            ],
            [
                'slug_prefix' => 'ancelot-honra',
                'name' => 'Ancelot Honor',
                'name_pt' => 'Honra de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_CHEST,
                'material' => Equipment::MATERIAL_LEATHER,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'power_rating' => 7750,
                'stats' => [
                    'hp' => 18000,
                    'defense' => 700,
                    'crit_rate_pct' => 10,
                    'dodge' => 50,
                ],
            ],
            [
                'slug_prefix' => 'ancelot-refugio',
                'name' => 'Ancelot Refuge',
                'name_pt' => 'Refúgio de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_HELMET,
                'material' => Equipment::MATERIAL_LEATHER,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'power_rating' => 7744,
                'stats' => [
                    'hp' => 11000,
                    'attack' => 1300,
                    'defense' => 110,
                    'accuracy' => 60,
                ],
            ],
            [
                'slug_prefix' => 'ancelot-lamina',
                'name' => 'Ancelot Blade',
                'name_pt' => 'Lâmina de Ancelot',
                'set_code' => 'ancelot',
                'slot' => Equipment::SLOT_WEAPON,
                'material' => Equipment::MATERIAL_LIGHT_WEAPON,
                'hero_type' => Equipment::HERO_TYPE_AGILITY,
                'power_rating' => 7715,
                'stats' => [
                    'attack' => 1800,
                    'dodge' => 85,
                    'accuracy' => 200,
                ],
            ],

            // === SET MÍSTICO — Heroes baseados em Inteligência (Padded + Magic Staff) ===
            [
                'slug_prefix' => 'mystic-botas',
                'name' => 'Mystic Boots',
                'name_pt' => 'Botas Místicas de Mágica',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_BOOTS,
                'material' => Equipment::MATERIAL_PADDED,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
                'power_rating' => 7735,
                'stats' => [
                    'hp' => 32000,
                    'attack' => 250,
                    'haste' => 10,
                    'dodge' => 100,
                ],
            ],
            [
                'slug_prefix' => 'mystic-casaco',
                'name' => 'Mystic Coat',
                'name_pt' => 'Casaco Místico de Mágica',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_CHEST,
                'material' => Equipment::MATERIAL_PADDED,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
                'power_rating' => 7742,
                'stats' => [
                    'hp' => 13000,
                    'attack' => 460,
                    'defense' => 650,
                    'magic_resistance_pct' => 10,
                ],
            ],
            [
                'slug_prefix' => 'mystic-chapeu',
                'name' => 'Mystic Hat',
                'name_pt' => 'Chapéu de Mística da Mágica',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_HELMET,
                'material' => Equipment::MATERIAL_PADDED,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
                'power_rating' => 7792,
                'stats' => [
                    'hp' => 14000,
                    'attack' => 1100,
                    'defense' => 120,
                    'crit_rate_pct' => 5,
                ],
            ],
            [
                'slug_prefix' => 'mystic-bastao',
                'name' => 'Mystic Magic Staff',
                'name_pt' => 'Bastão de Magia do Místico',
                'set_code' => 'mystic',
                'slot' => Equipment::SLOT_WEAPON,
                'material' => Equipment::MATERIAL_MAGIC_STAFF,
                'hero_type' => Equipment::HERO_TYPE_INTELLIGENCE,
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
