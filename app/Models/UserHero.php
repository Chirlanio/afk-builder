<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHero extends Model
{
    public const EQUIPMENT_TIERS = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6'];

    public const EQUIPMENT_TIER_LABELS = [
        'T1' => 'Comum',
        'T2' => 'Raro',
        'T3' => 'Elite',
        'T4' => 'Lendário',
        'T5' => 'Mítico',
        'T6' => 'Ascendido',
    ];

    public const EQUIPMENT_LEVEL_MAX = 20;

    public const EQUIPMENT_SLOTS = ['weapon', 'helmet', 'chest', 'boots'];

    /** Stats reusam o schema de HeroStat (ver HeroStat::CORE_FIELDS / EXTRA_FIELDS). */
    public const STAT_FIELDS = [
        'hp', 'attack', 'defense',
        'crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste',
        'vitality', 'armor', 'attack_speed', 'healing', 'recovery_per_second', 'energy_recovery_rate',
        'crit_block_rate', 'crit_damage_resistance',
        'physical_piercing', 'magic_piercing', 'physical_resistance', 'magic_resistance', 'magic_efficiency', 'magic_suppression',
        'life_steal', 'tenacity', 'damage_resistance', 'perception',
        'proficiency', 'dispel', 'erosion', 'aid',
        'draconic_amplification', 'draconic_resistance',
        'arena_damage_boost', 'arena_damage_reduction', 'trial_damage_boost',
    ];

    protected $fillable = [
        'user_id',
        'hero_id',
        'level',
        'ascension_code',
        'ascension_value',
        'signature_item',
        'furniture',
        'engraving',
        'weapon_tier', 'weapon_level',
        'helmet_tier', 'helmet_level',
        'chest_tier', 'chest_level',
        'boots_tier', 'boots_level',
        'artifact_id',
        'artifact_star_level',
        'collection_id',
        'power_rating',
        'notes',
        ...self::STAT_FIELDS,
    ];

    protected $casts = [
        'level' => 'integer',
        'ascension_value' => 'integer',
        'signature_item' => 'integer',
        'furniture' => 'integer',
        'engraving' => 'integer',
        'weapon_level' => 'integer',
        'helmet_level' => 'integer',
        'chest_level' => 'integer',
        'boots_level' => 'integer',
        'artifact_star_level' => 'integer',
        'power_rating' => 'integer',
        // Stats reais do roster
        'hp' => 'integer',
        'attack' => 'integer',
        'defense' => 'integer',
        'crit_rate' => 'float',
        'crit_amp' => 'float',
        'accuracy' => 'float',
        'dodge' => 'float',
        'haste' => 'float',
        'vitality' => 'float',
        'armor' => 'float',
        'attack_speed' => 'float',
        'healing' => 'float',
        'recovery_per_second' => 'float',
        'energy_recovery_rate' => 'float',
        'crit_block_rate' => 'float',
        'crit_damage_resistance' => 'float',
        'physical_piercing' => 'float',
        'magic_piercing' => 'float',
        'physical_resistance' => 'float',
        'magic_resistance' => 'float',
        'magic_efficiency' => 'float',
        'magic_suppression' => 'float',
        'life_steal' => 'float',
        'tenacity' => 'float',
        'damage_resistance' => 'float',
        'perception' => 'float',
        'proficiency' => 'float',
        'dispel' => 'float',
        'erosion' => 'float',
        'aid' => 'float',
        'draconic_amplification' => 'float',
        'draconic_resistance' => 'float',
        'arena_damage_boost' => 'float',
        'arena_damage_reduction' => 'float',
        'trial_damage_boost' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(Artifact::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
