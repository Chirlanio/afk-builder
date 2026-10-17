<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Artifact extends Model
{
    public const KIND_DURA = 'dura';

    public const KIND_CLASS = 'class';

    protected $fillable = [
        'slug',
        'name',
        'kind',
        'hero_class_id',
        'description',
        'attributes',
        'powers',
        'obtained_in',
        'image_filename',
        'portrait_url',
        'source_url',
        'max_stars',
        'is_active',
    ];

    protected $casts = [
        'attributes' => 'array',
        'powers' => 'array',
        'is_active' => 'boolean',
        'max_stars' => 'integer',
    ];

    public function heroClass(): BelongsTo
    {
        return $this->belongsTo(HeroClass::class);
    }

    public function userHeroes(): HasMany
    {
        return $this->hasMany(UserHero::class);
    }
}
