<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beast extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'strength_buff',
        'intelligence_buff',
        'agility_buff',
        'portrait_url',
        'source_url',
        'is_active',
    ];

    protected $casts = [
        'strength_buff' => 'integer',
        'intelligence_buff' => 'integer',
        'agility_buff' => 'integer',
        'is_active' => 'boolean',
    ];

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }
}
