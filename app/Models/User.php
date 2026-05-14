<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function userHeroes(): HasMany
    {
        return $this->hasMany(UserHero::class);
    }

    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }

    public function tierLists(): HasMany
    {
        return $this->hasMany(UserTierList::class);
    }

    public function scoringWeightPresets(): HasMany
    {
        return $this->hasMany(ScoringWeightPreset::class);
    }

    public function formationTags(): HasMany
    {
        return $this->hasMany(FormationTag::class);
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
