<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormationTeam extends Model
{
    protected $fillable = [
        'formation_id',
        'position',
        'name',
        'score',
        'faction_bonuses',
    ];

    protected $casts = [
        'position' => 'integer',
        'score' => 'integer',
        'faction_bonuses' => 'array',
    ];

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    public function teamHeroes(): HasMany
    {
        return $this->hasMany(FormationTeamHero::class)->orderBy('slot');
    }
}
