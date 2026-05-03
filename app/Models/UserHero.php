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
