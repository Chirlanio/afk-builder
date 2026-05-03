<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hero extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'title',
        'faction_id',
        'hero_class_id',
        'tier_id',
        'gender',
        'type',
        'role_label',
        'special_mechanic',
        'portrait_url',
        'thumbnail_url',
        'source_url',
        'lore',
        'lore_pt',
        'released_at',
        'is_active',
        'pending_review',
    ];

    protected $casts = [
        'released_at' => 'date',
        'is_active' => 'boolean',
        'pending_review' => 'boolean',
    ];

    public function faction(): BelongsTo
    {
        return $this->belongsTo(Faction::class);
    }

    public function heroClass(): BelongsTo
    {
        return $this->belongsTo(HeroClass::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    public function abilities(): HasMany
    {
        return $this->hasMany(HeroAbility::class)->orderBy('display_order');
    }

    public function furnitureAbility(): HasOne
    {
        return $this->hasOne(HeroFurnitureAbility::class);
    }

    public function userHeroes(): HasMany
    {
        return $this->hasMany(UserHero::class);
    }

    public function synergiesAsA(): HasMany
    {
        return $this->hasMany(HeroSynergy::class, 'hero_a_id');
    }

    public function synergiesAsB(): HasMany
    {
        return $this->hasMany(HeroSynergy::class, 'hero_b_id');
    }

    public function synergyPartners(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'hero_synergies', 'hero_a_id', 'hero_b_id')
            ->withPivot(['weight', 'note'])
            ->withTimestamps();
    }
}
