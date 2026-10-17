<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHero extends Model
{
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
        'game_account_id',
        'hero_id',
        'level',
        'ascension_code',
        'ascension_value',
        'signature_item',
        'furniture',
        'engraving',
        'weapon_equipment_id', 'weapon_equipment_level',
        'helmet_equipment_id', 'helmet_equipment_level',
        'chest_equipment_id', 'chest_equipment_level',
        'boots_equipment_id', 'boots_equipment_level',
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
        'artifact_star_level' => 'integer',
        'power_rating' => 'integer',
        'weapon_equipment_level' => 'integer',
        'helmet_equipment_level' => 'integer',
        'chest_equipment_level' => 'integer',
        'boots_equipment_level' => 'integer',
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

    public function gameAccount(): BelongsTo
    {
        return $this->belongsTo(GameAccount::class);
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

    public function weaponEquipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'weapon_equipment_id');
    }

    public function helmetEquipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'helmet_equipment_id');
    }

    public function chestEquipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'chest_equipment_id');
    }

    public function bootsEquipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'boots_equipment_id');
    }

    /**
     * Helper para iteração sobre os 4 slots de equipamento mítico equipado.
     * Retorna pares [Equipment, level] (skipa slots vazios).
     *
     * @return array<int, array{0: Equipment, 1: int}>
     */
    public function equippedMythicItems(): array
    {
        $pairs = [];
        foreach (['weapon', 'helmet', 'chest', 'boots'] as $slot) {
            $relation = $slot.'Equipment';
            if (! $this->relationLoaded($relation)) {
                continue;
            }
            $equipment = $this->{$relation};
            if ($equipment instanceof Equipment) {
                $pairs[] = [$equipment, (int) $this->{$slot.'_equipment_level'}];
            }
        }

        return $pairs;
    }
}
