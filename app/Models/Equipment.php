<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    /** Override do auto-plural — Laravel pluraliza 'equipment' como 'equipment' (incontável). */
    protected $table = 'equipments';

    public const SLOT_BOOTS = 'boots';

    public const SLOT_CHEST = 'chest';

    public const SLOT_HELMET = 'helmet';

    public const SLOT_WEAPON = 'weapon';

    public const SLOTS = [
        self::SLOT_BOOTS,
        self::SLOT_CHEST,
        self::SLOT_HELMET,
        self::SLOT_WEAPON,
    ];

    public const MATERIAL_PLATE = 'plate';

    public const MATERIAL_LEATHER = 'leather';

    public const MATERIAL_PADDED = 'padded';

    public const MATERIAL_HEAVY_WEAPON = 'heavy_weapon';

    public const MATERIAL_LIGHT_WEAPON = 'light_weapon';

    public const MATERIAL_MAGIC_STAFF = 'magic_staff';

    public const HERO_TYPE_STRENGTH = 'strength';

    public const HERO_TYPE_AGILITY = 'agility';

    public const HERO_TYPE_INTELLIGENCE = 'intelligence';

    public const QUALITY_MYTHIC = 'mythic';

    public const QUALITY_MYTHIC_T1 = 'mythic_t1';

    public const QUALITY_MYTHIC_T2 = 'mythic_t2';

    public const QUALITY_MYTHIC_T3 = 'mythic_t3';

    public const QUALITY_MYTHIC_T4 = 'mythic_t4';

    public const QUALITIES = [
        self::QUALITY_MYTHIC,
        self::QUALITY_MYTHIC_T1,
        self::QUALITY_MYTHIC_T2,
        self::QUALITY_MYTHIC_T3,
        self::QUALITY_MYTHIC_T4,
    ];

    /** Bônus de facção: 30% nos stats quando equipado em hero da mesma faction. */
    public const FACTION_BONUS_PCT = 30;

    protected $fillable = [
        'slug',
        'name',
        'name_pt',
        'set_code',
        'slot',
        'material',
        'hero_type',
        'faction_id',
        'quality_tier',
        'power_rating',
        'stats',
        'set_bonuses',
        'max_upgrade_level',
        'upgrade_percent_per_level',
        'is_active',
    ];

    protected $casts = [
        'stats' => 'array',
        'set_bonuses' => 'array',
        'power_rating' => 'integer',
        'max_upgrade_level' => 'integer',
        'upgrade_percent_per_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    /**
     * Power rating com o upgrade aplicado (+10% do base por nível por default).
     */
    public function powerAtLevel(int $upgradeLevel): int
    {
        $clamped = max(0, min($this->max_upgrade_level, $upgradeLevel));
        $multiplier = 1.0 + ($clamped * $this->upgrade_percent_per_level / 100.0);

        return (int) round($this->power_rating * $multiplier);
    }

    /**
     * Retorna os stats com o multiplier de upgrade aplicado em cada valor.
     * Stats `_pct` também escalam (ex.: CRIT 24% no L0 → 26.4% no L1 com 10%/level).
     *
     * @return array<string, float|int>
     */
    public function statsAtLevel(int $upgradeLevel): array
    {
        $clamped = max(0, min($this->max_upgrade_level, $upgradeLevel));
        $multiplier = 1.0 + ($clamped * $this->upgrade_percent_per_level / 100.0);

        $result = [];
        foreach ($this->stats ?? [] as $key => $value) {
            $result[$key] = round((float) $value * $multiplier, 2);
        }

        return $result;
    }

    /**
     * Aplica o bônus de facção (+30% nos stats) quando o hero pertence à mesma
     * faction do equipamento. Recebe stats já com upgrade aplicado.
     *
     * @param  array<string, float|int>  $stats
     * @return array<string, float|int>
     */
    public function applyFactionBonus(array $stats, bool $factionMatches): array
    {
        if (! $factionMatches) {
            return $stats;
        }
        $multiplier = 1.0 + (self::FACTION_BONUS_PCT / 100.0);
        $result = [];
        foreach ($stats as $key => $value) {
            $result[$key] = round((float) $value * $multiplier, 2);
        }

        return $result;
    }

    public function hasUpgrades(): bool
    {
        return $this->max_upgrade_level > 0;
    }
}
