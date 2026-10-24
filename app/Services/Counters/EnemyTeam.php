<?php

namespace App\Services\Counters;

use App\Models\Hero;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Equipe inimiga vinda da URL (`enemy[posição]=id`): 1 a 5 heróis nas
 * posições 1-5, sem herói repetido, sem herói pendente de revisão e sem as
 * duas versões do mesmo Personagem. Usada pela página de Counters e pelo
 * Export para IA.
 */
class EnemyTeam
{
    public const MAX = 5;

    /** Posições 1 até esta são a linha de frente; as demais, a de trás. */
    public const FRONTLINE = 2;

    /**
     * @return Collection<int, array{position: int, hero: array<string, mixed>}>
     */
    public static function fromRequest(Request $request): Collection
    {
        $request->validate([
            'enemy' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'enemy.*' => ['integer', Rule::exists('heroes', 'id')->where('is_active', true)->where('pending_review', 0)],
        ]);

        $enemy = collect($request->input('enemy'));
        if ($enemy->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['enemy' => 'Um herói não pode aparecer duas vezes na Equipe inimiga.']);
        }
        $invalidPosition = $enemy->keys()->first(fn ($position) => preg_match('/^[1-'.self::MAX.']$/', (string) $position) !== 1);
        if ($invalidPosition !== null) {
            throw ValidationException::withMessages(['enemy' => 'As posições da Equipe inimiga vão de 1 a '.self::MAX.'.']);
        }

        $heroes = Hero::whereIn('id', $enemy->values())->get(['id', 'slug', 'name', 'portrait_url'])->keyBy('id');
        if ($heroes->map(fn (Hero $hero) => $hero->characterKey())->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['enemy' => 'A versão base e a Desperta de um herói são o mesmo Personagem: use só uma delas.']);
        }

        return $enemy
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
