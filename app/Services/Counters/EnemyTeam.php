<?php

namespace App\Services\Counters;

use App\Models\Hero;
use Illuminate\Support\Collection;

/**
 * Equipe inimiga: 1 a 5 heróis por posição, a linha de frente nas primeiras.
 * A entrada chega já validada por App\Rules\ValidEnemyTeam.
 */
class EnemyTeam
{
    public const MAX = 5;

    /** Posições 1 até esta são a linha de frente; as demais, a de trás. */
    public const FRONTLINE = 2;

    /**
     * @param  array<int|string, int|string>  $enemy  posição => id do herói
     * @return Collection<int, array{position: int, hero: array<string, mixed>}>
     */
    public static function fromIds(array $enemy): Collection
    {
        $heroes = Hero::whereIn('id', array_values($enemy))->get(['id', 'slug', 'name', 'portrait_url'])->keyBy('id');

        return collect($enemy)
            ->map(fn ($heroId, $position) => [
                'position' => (int) $position,
                'hero' => $heroes[(int) $heroId]->only(['id', 'slug', 'name', 'portrait_url']),
            ])
            ->sortBy('position')
            ->values();
    }

    public static function isFrontline(int $position): bool
    {
        return $position <= self::FRONTLINE;
    }
}
