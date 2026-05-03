<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroFurnitureAbility extends Model
{
    protected $fillable = [
        'hero_id',
        'name',
        'name_pt',
        'f3_description',
        'f3_description_pt',
        'f9_description',
        'f9_description_pt',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
