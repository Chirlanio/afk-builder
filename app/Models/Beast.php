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
        'buffs_strength',
        'buffs_intelligence',
        'buffs_agility',
        'buffs_team',
        'tier',
        'tier_note',
        'portrait_url',
        'source_url',
        'is_active',
    ];

    protected $casts = [
        'buffs_strength' => 'array',
        'buffs_intelligence' => 'array',
        'buffs_agility' => 'array',
        'buffs_team' => 'array',
        'is_active' => 'boolean',
    ];

    public const MAX_LEVEL = 36;

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }
}
