<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormationTeamHero extends Model
{
    protected $fillable = [
        'formation_team_id',
        'hero_id',
        'slot',
        'row',
    ];

    protected $casts = [
        'slot' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(FormationTeam::class, 'formation_team_id');
    }

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }
}
