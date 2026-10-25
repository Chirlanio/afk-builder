<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
        'capabilities',
        'capabilities_manual',
    ];

    protected $casts = [
        'released_at' => 'date',
        'is_active' => 'boolean',
        'pending_review' => 'boolean',
        'capabilities' => 'array',
        'capabilities_manual' => 'array',
    ];

    /**
     * Heróis que o jogador vê no catálogo: ativos e já revisados.
     *
     * @param  Builder<Hero>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_active', true)->where('pending_review', false);
    }

    /**
     * Herói Desperto (Awakened): o slug da wiki é o nome + o título da versão
     * desperta ("shemira" → "shemira-the-soulreaver"). Comparar só slug ≠ nome
     * marcava falsos positivos quando o NOME é mais longo que o slug
     * ("Ukyo Tachibana" / "ukyo").
     */
    public static function isAwakenedSlug(?string $name, ?string $slug): bool
    {
        if ($name === null || $slug === null) {
            return false;
        }

        $base = Str::slug($name);

        return $slug !== $base && str_starts_with($slug, $base.'-');
    }

    public function isAwakened(): bool
    {
        return self::isAwakenedSlug($this->name, $this->slug);
    }

    /**
     * Identifies the character regardless of version: a hero and its Awakened
     * form share the same name ("Shemira" / "shemira-the-soulreaver") and the
     * game does not allow both in a lineup.
     */
    public function characterKey(): string
    {
        return Str::slug($this->name ?? '') ?: (string) $this->slug;
    }

    /**
     * Perfil de capacidades efetivo: o do classificador com as correções do
     * admin aplicadas por cima (remove primeiro, depois add/sobrescreve força).
     *
     * @return array<string, int>
     */
    public function effectiveCapabilities(): array
    {
        $profile = $this->capabilities ?? [];
        $manual = $this->capabilities_manual ?? [];

        foreach ($manual['remove'] ?? [] as $tag) {
            unset($profile[$tag]);
        }
        foreach ($manual['add'] ?? [] as $tag => $strength) {
            $profile[$tag] = (int) $strength;
        }

        return $profile;
    }

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

    public function stats(): HasOne
    {
        return $this->hasOne(HeroStat::class);
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

    /** Counters em que este Herói é forte contra outro. */
    public function counters(): HasMany
    {
        return $this->hasMany(HeroCounter::class);
    }

    /** Counters em que este Herói é o alvo. */
    public function counteredBy(): HasMany
    {
        return $this->hasMany(HeroCounter::class, 'target_hero_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(HeroRating::class);
    }

    public function engravings(): HasMany
    {
        return $this->hasMany(HeroEngraving::class)->orderBy('level');
    }

    public function features(): HasMany
    {
        return $this->hasMany(HeroFeature::class);
    }
}
