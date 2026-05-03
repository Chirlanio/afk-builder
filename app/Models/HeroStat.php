<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroStat extends Model
{
    protected $fillable = [
        'hero_id',
        'hp',
        'attack',
        'defense',
        'crit_rate',
        'crit_amp',
        'accuracy',
        'dodge',
        'haste',
    ];

    protected $casts = [
        'hp' => 'integer',
        'attack' => 'integer',
        'defense' => 'integer',
        'crit_rate' => 'integer',
        'crit_amp' => 'integer',
        'accuracy' => 'integer',
        'dodge' => 'integer',
        'haste' => 'integer',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
