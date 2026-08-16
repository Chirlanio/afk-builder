<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringWeightPreset extends Model
{
    public const WEIGHT_KEYS = [
        'power_weight',
        'synergy_weight',
        'faction_bonus_weight',
        'class_balance_weight',
        'tier_rating_weight',
        'feature_weight',
        'furniture_effect_weight',
        'engraving_weight',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'challenge_type_id',
        'weights',
    ];

    protected $casts = [
        'weights' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameAccount(): BelongsTo
    {
        return $this->belongsTo(GameAccount::class);
    }

    public function challengeType(): BelongsTo
    {
        return $this->belongsTo(ChallengeType::class);
    }
}
