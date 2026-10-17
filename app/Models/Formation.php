<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Formation extends Model
{
    protected $fillable = [
        'game_account_id',
        'challenge_type_id',
        'name',
        'mode',
        'teams_count',
        'notes',
        'total_score',
        'share_token',
        'scoring_weights_override',
    ];

    protected $casts = [
        'teams_count' => 'integer',
        'total_score' => 'integer',
        'scoring_weights_override' => 'array',
    ];

    public function gameAccount(): BelongsTo
    {
        return $this->belongsTo(GameAccount::class);
    }

    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, GameAccount::class, 'id', 'id', 'game_account_id', 'user_id');
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            FormationTag::class,
            'formation_tag_assignments',
            'formation_id',
            'formation_tag_id',
        )->withTimestamps();
    }
}
