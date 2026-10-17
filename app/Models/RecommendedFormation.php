<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RecommendedFormation extends Model
{
    public const MODE_PVP = 'pvp';

    public const MODE_PVE = 'pve';

    public const MODE_TWISTED_REALM = 'twisted_realm';

    public const MODE_LABYRINTH = 'labyrinth';

    public const MODE_KING_TOWER = 'king_tower';

    public const MODE_ARCANE_LABYRINTH = 'arcane_labyrinth';

    protected $fillable = [
        'slug',
        'name',
        'mode',
        'tier',
        'level_deficit',
        'description',
        'notes',
        'hero_slugs',
        'core_count',
        'artifacts',
        'source_url',
        'source_label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'hero_slugs' => 'array',
        'artifacts' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'core_count' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return Collection<int, Hero>
     */
    public function resolveHeroes(): Collection
    {
        $slugs = $this->hero_slugs ?? [];
        if ($slugs === []) {
            return Hero::query()->whereRaw('1 = 0')->get();
        }

        return Hero::query()->whereIn('slug', $slugs)->get();
    }
}
