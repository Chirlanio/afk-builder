<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroFurnitureAbility extends Model
{
    protected $fillable = [
        'hero_id',
        'name',
        'f3_description',
        'f9_description',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
