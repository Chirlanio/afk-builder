<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroRating extends Model
{
    public const MODE_OVERALL = 'overall';
    public const MODE_PVP = 'pvp';
    public const MODE_PVE = 'pve';
    public const MODE_LABYRINTH = 'labyrinth';
    public const MODE_TWISTED_REALM = 'twisted_realm';
    public const MODE_TEMPORAL_RIFT = 'temporal_rift';
    public const MODE_ABYSSAL_EXPEDITION = 'abyssal_expedition';

    public const STAGE_EARLY = 'early';
    public const STAGE_MID = 'mid';
    public const STAGE_END = 'end';

    public const TIERS = ['S+', 'S', 'A', 'B', 'C', 'D', 'F'];

    protected $fillable = [
        'hero_id',
        'mode',
        'stage',
        'tier',
        'source',
        'source_url',
        'sync_batch',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
