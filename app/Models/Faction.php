<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faction extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_pt',
        'color_hex',
        'icon_url',
        'advantage_over_faction_id',
        'special_behavior',
        'display_order',
    ];

    protected $casts = [
        'special_behavior' => 'array',
        'display_order' => 'integer',
    ];

    public function advantageOver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'advantage_over_faction_id');
    }

    public function counteredBy(): HasMany
    {
        return $this->hasMany(self::class, 'advantage_over_faction_id');
    }

    public function heroes(): HasMany
    {
        return $this->hasMany(Hero::class);
    }
}
