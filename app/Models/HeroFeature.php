<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroFeature extends Model
{
    public const TAG_EARLY_CARRY = 'early_carry';

    public const TAG_EARLY_SUPPORT = 'early_support';

    public const TAG_EARLY_TANK = 'early_tank';

    public const TAG_EARLY_UTILITY = 'early_utility';

    public const TAG_PVP_TOP = 'pvp_top';

    public const TAG_TWISTED_REALM_TOP = 'twisted_realm_top';

    public const TAGS = [
        self::TAG_EARLY_CARRY,
        self::TAG_EARLY_SUPPORT,
        self::TAG_EARLY_TANK,
        self::TAG_EARLY_UTILITY,
        self::TAG_PVP_TOP,
        self::TAG_TWISTED_REALM_TOP,
    ];

    protected $fillable = [
        'hero_id',
        'tag',
        'note',
        'source',
        'source_url',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
