<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroEngraving extends Model
{
    public const LEVEL_E30 = 'e30';

    public const LEVEL_E60 = 'e60';

    protected $fillable = [
        'hero_id',
        'level',
        'label',
        'description',
        'source',
        'source_url',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
