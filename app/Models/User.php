<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function gameAccounts(): HasMany
    {
        return $this->hasMany(GameAccount::class);
    }

    public function defaultGameAccount(): HasOne
    {
        return $this->hasOne(GameAccount::class)->where('is_default', true);
    }

    // Relações agregadas cross-account (retro-compat): percorrem todas as
    // contas do user. Para dados scoped à conta ativa, use $account->relation
    // via ActiveGameAccount service.

    public function userHeroes(): HasManyThrough
    {
        return $this->hasManyThrough(UserHero::class, GameAccount::class);
    }

    public function formations(): HasManyThrough
    {
        return $this->hasManyThrough(Formation::class, GameAccount::class);
    }

    public function tierLists(): HasManyThrough
    {
        return $this->hasManyThrough(UserTierList::class, GameAccount::class);
    }

    public function scoringWeightPresets(): HasManyThrough
    {
        return $this->hasManyThrough(ScoringWeightPreset::class, GameAccount::class);
    }

    public function formationTags(): HasManyThrough
    {
        return $this->hasManyThrough(FormationTag::class, GameAccount::class);
    }

    public function rosterPowerSnapshots(): HasManyThrough
    {
        return $this->hasManyThrough(RosterPowerSnapshot::class, GameAccount::class);
    }

    public function resources(): HasOneThrough
    {
        return $this->hasOneThrough(UserResource::class, GameAccount::class);
    }

    public function resonatingCrystal(): HasOneThrough
    {
        return $this->hasOneThrough(UserResonatingCrystal::class, GameAccount::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const LOCALES = ['en', 'pt'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
