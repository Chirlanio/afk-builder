<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'hp_bonus',
        'attack_bonus',
        'defense_bonus',
        'portrait_url',
        'source_url',
        'is_active',
    ];

    protected $casts = [
        'hp_bonus' => 'integer',
        'attack_bonus' => 'integer',
        'defense_bonus' => 'integer',
        'is_active' => 'boolean',
    ];

    public function userHeroes(): HasMany
    {
        return $this->hasMany(UserHero::class);
    }
}
