<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tier extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_pt',
        'max_level',
        'display_order',
    ];

    protected $casts = [
        'max_level' => 'integer',
        'display_order' => 'integer',
    ];

    public function heroes(): HasMany
    {
        return $this->hasMany(Hero::class);
    }
}
