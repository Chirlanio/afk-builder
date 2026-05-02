<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroSynergy extends Model
{
    protected $fillable = [
        'hero_a_id',
        'hero_b_id',
        'weight',
        'note',
    ];

    protected $casts = [
        'weight' => 'integer',
    ];

    public function heroA(): BelongsTo
    {
        return $this->belongsTo(Hero::class, 'hero_a_id');
    }

    public function heroB(): BelongsTo
    {
        return $this->belongsTo(Hero::class, 'hero_b_id');
    }
}
