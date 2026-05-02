<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChallengeType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_pt',
        'description',
        'min_teams',
        'max_teams',
        'is_pvp',
        'teams_must_be_unique',
        'scoring_profile',
        'display_order',
    ];

    protected $casts = [
        'min_teams' => 'integer',
        'max_teams' => 'integer',
        'is_pvp' => 'boolean',
        'teams_must_be_unique' => 'boolean',
        'scoring_profile' => 'array',
        'display_order' => 'integer',
    ];

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }
}
