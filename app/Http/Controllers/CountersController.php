<?php

namespace App\Http\Controllers;

use App\Enums\Planning;
use App\Models\Hero;
use App\Models\HeroCounter;
use App\Models\HeroRating;
use App\Services\ActiveGameAccount;
use App\Services\Counters\CounterRanking;
use App\Services\Optimization\RosterStageInferrer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página "Counters": o jogador monta a Equipe inimiga (posição => herói, na
 * URL) e vê os heróis do Roster que a counteram.
 */
class CountersController extends Controller
{
    private const MAX_ENEMY = 5;

    /** Planejamento alvo: o ranking mostra os primeiros 15 até o jogador pedir todos. */
    private const TARGET_RANKING_PAGE_SIZE = 15;

    public function __construct(
        private readonly ActiveGameAccount $active,
        private readonly CounterRanking $ranking,
        private readonly RosterStageInferrer $stageInferrer,
    ) {}

    public function index(Request $request): Response
    {
        $contexts = HeroCounter::availableContexts();
        $filters = $request->validate([
            'context' => ['nullable', Rule::in($contexts)],
            'planning' => ['nullable', Rule::enum(Planning::class)],
            'all' => ['nullable', 'boolean'],
        ]);
        $context = $filters['context'] ?? HeroRating::MODE_PVP;
        $planning = Planning::tryFrom($filters['planning'] ?? '') ?? Planning::Real;

        $enemy = $request->has('enemy') ? $this->validatedEnemy($request) : collect();

        $ranking = [];
        // Seção inversa: só no real, porque no alvo não há Roster a proteger.
        $threatened = $planning === Planning::Real ? [] : null;
        $enemyIds = $enemy->pluck('hero.id')->all();
        if ($enemy->isNotEmpty() && $planning === Planning::Target) {
            // Alvo: qualquer herói ativo do jogo, no fim de jogo.
            $ranking = $this->ranking->rank($enemyIds, null, $context, HeroRating::STAGE_END);
        } elseif ($enemy->isNotEmpty()) {
            $userHeroes = $this->active->get()->userHeroes()->get(['hero_id', 'ascension_value']);
            $rosterIds = $userHeroes->pluck('hero_id')->all();
            $ranking = $this->ranking->rank($enemyIds, $rosterIds, $context, $this->stageInferrer->inferFromRoster($userHeroes));
            $threatened = $this->ranking->threatened($enemyIds, $rosterIds, $context);
        }
        $showAll = $planning === Planning::Real || $request->boolean('all');

        return Inertia::render('counters/index', [
            'heroes' => Hero::query()
                ->where('is_active', true)
                ->where('pending_review', false)
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'portrait_url']),
            'enemy' => $enemy->values()->all(),
            'contexts' => $contexts,
            'context' => $context,
            'planning' => $planning->value,
            'ranking' => $showAll ? $ranking : array_slice($ranking, 0, self::TARGET_RANKING_PAGE_SIZE),
            'rankingTotal' => count($ranking),
            'threatened' => $threatened,
        ]);
    }

    /**
     * Equipe inimiga: 1 a 5 heróis nas posições 1-5, sem herói repetido nem as
     * duas versões do mesmo Personagem.
     *
     * @return Collection<int, array{position: int, hero: array<string, mixed>}>
     */
    private function validatedEnemy(Request $request): Collection
    {
        $request->validate([
            'enemy' => ['required', 'array', 'min:1', 'max:'.self::MAX_ENEMY],
            'enemy.*' => ['integer', Rule::exists('heroes', 'id')->where('is_active', true)->where('pending_review', 0)],
        ]);

        $enemy = collect($request->input('enemy'));
        if ($enemy->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['enemy' => 'Um herói não pode aparecer duas vezes na Equipe inimiga.']);
        }
        $invalidPosition = $enemy->keys()->first(fn ($position) => preg_match('/^[1-'.self::MAX_ENEMY.']$/', (string) $position) !== 1);
        if ($invalidPosition !== null) {
            throw ValidationException::withMessages(['enemy' => 'As posições da Equipe inimiga vão de 1 a '.self::MAX_ENEMY.'.']);
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
            ->sortBy('position');
    }
}
