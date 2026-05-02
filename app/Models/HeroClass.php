<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeroClass extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_pt',
        'icon_url',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function heroes(): HasMany
    {
        return $this->hasMany(Hero::class);
    }
}
