<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroAbility extends Model
{
    protected $fillable = [
        'hero_id',
        'name',
        'name_pt',
        'type',
        'display_order',
        'ascension_unlock',
        'description',
        'description_pt',
        'effects',
    ];

    protected $casts = [
        'effects' => 'array',
        'display_order' => 'integer',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
