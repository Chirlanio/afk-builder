<?php

namespace App\Models;

use Database\Factories\GameAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GameAccount extends Model
{
    /** @use HasFactory<GameAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'server',
        'avatar_hero_id',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function avatarHero(): BelongsTo
    {
        return $this->belongsTo(Hero::class, 'avatar_hero_id');
    }

    public function userHeroes(): HasMany
    {
        return $this->hasMany(UserHero::class);
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }

    public function formationTags(): HasMany
    {
        return $this->hasMany(FormationTag::class);
    }

    public function tierLists(): HasMany
    {
        return $this->hasMany(UserTierList::class);
    }

    public function scoringWeightPresets(): HasMany
    {
        return $this->hasMany(ScoringWeightPreset::class);
    }

    public function rosterPowerSnapshots(): HasMany
    {
        return $this->hasMany(RosterPowerSnapshot::class)->orderBy('snapshot_date');
    }

    public function resources(): HasOne
    {
        return $this->hasOne(UserResource::class);
    }

    public function resonatingCrystal(): HasOne
    {
        return $this->hasOne(UserResonatingCrystal::class);
    }
}
