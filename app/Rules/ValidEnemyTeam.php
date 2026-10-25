<?php

namespace App\Rules;

use App\Models\Hero;
use App\Services\Counters\EnemyTeam;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Equipe inimiga vinda da URL (`enemy[posição]=id`): posições 1-5, sem herói
 * repetido e sem as duas versões do mesmo Personagem. Usada pela página de
 * Counters e pelo Export para IA.
 */
class ValidEnemyTeam implements ValidationRule
{
    /**
     * Regras completas do campo `enemy`, com esta regra e a dos heróis.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'enemy' => ['required', 'array', 'min:1', 'max:'.EnemyTeam::MAX, new self],
            // pending_review como 0: o exists compara o valor cru, e false não casa no MySQL.
            'enemy.*' => ['integer', Rule::exists('heroes', 'id')->where('is_active', true)->where('pending_review', 0)],
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $enemy = collect($value);
        if ($enemy->duplicates()->isNotEmpty()) {
            $fail('Um herói não pode aparecer duas vezes na Equipe inimiga.');

            return;
        }
        $invalidPosition = $enemy->keys()->first(fn ($position) => preg_match('/^[1-'.EnemyTeam::MAX.']$/', (string) $position) !== 1);
        if ($invalidPosition !== null) {
            $fail('As posições da Equipe inimiga vão de 1 a '.EnemyTeam::MAX.'.');

            return;
        }

        $heroes = Hero::whereIn('id', $enemy->filter(fn ($id) => is_numeric($id))->values())->get(['id', 'slug', 'name']);
        if ($heroes->map(fn (Hero $hero) => $hero->characterKey())->duplicates()->isNotEmpty()) {
            $fail('A versão base e a Desperta de um herói são o mesmo Personagem: use só uma delas.');
        }
    }
}
