<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_type_id',
        'name',
        'mode',
        'teams_count',
        'notes',
        'total_score',
        'share_token',
        'beast_id',
    ];

    protected $casts = [
        'teams_count' => 'integer',
        'total_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function challengeType(): BelongsTo
    {
        return $this->belongsTo(ChallengeType::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(FormationTeam::class)->orderBy('position');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormationVersion::class)->orderByDesc('created_at');
    }

    public function beast(): BelongsTo
    {
        return $this->belongsTo(Beast::class);
    }
}
