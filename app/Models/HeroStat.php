<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroStat extends Model
{
    /** Campos numéricos básicos (compartilhados com user_heroes para stats reais do roster). */
    public const CORE_FIELDS = ['hp', 'attack', 'defense', 'crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste'];

    public const EXTRA_FIELDS = [
        'vitality', 'armor', 'attack_speed', 'healing', 'recovery_per_second', 'energy_recovery_rate',
        'crit_block_rate', 'crit_damage_resistance',
        'physical_piercing', 'magic_piercing', 'physical_resistance', 'magic_resistance', 'magic_efficiency', 'magic_suppression',
        'life_steal', 'tenacity', 'damage_resistance', 'perception',
        'proficiency', 'dispel', 'erosion', 'aid',
        'draconic_amplification', 'draconic_resistance',
        'arena_damage_boost', 'arena_damage_reduction', 'trial_damage_boost',
    ];

    protected $fillable = [
        'hero_id',
        'hp', 'attack', 'defense',
        'crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste',
        ...self::EXTRA_FIELDS,
    ];

    protected $casts = [
        'hp' => 'integer',
        'attack' => 'integer',
        'defense' => 'integer',
        'crit_rate' => 'float',
        'crit_amp' => 'float',
        'accuracy' => 'float',
        'dodge' => 'float',
        'haste' => 'float',
        // Extras (Fase 24.8) — todos float
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

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
