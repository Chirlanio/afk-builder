<?php

namespace App\Http\Controllers;

use App\Enums\Planning;
use App\Models\Hero;
use App\Models\HeroCounter;
use App\Models\HeroRating;
use App\Rules\ValidEnemyTeam;
use App\Services\ActiveGameAccount;
use App\Services\Counters\CounterRanking;
use App\Services\Counters\EnemyTeam;
use App\Services\Optimization\RosterStageInferrer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página "Counters": o jogador monta a Equipe inimiga (posição => herói, na
 * URL) e vê os heróis do Roster que a counteram.
 */
class CountersController extends Controller
{
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

        $enemy = $request->has('enemy') ? EnemyTeam::fromIds($request->validate(ValidEnemyTeam::rules())['enemy']) : collect();

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
                ->available()
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'portrait_url']),
            'enemy' => $enemy->values()->all(),
            'contexts' => $contexts,
            'context' => $context,
            'planning' => $planning->value,
            'ranking' => $showAll ? $ranking : array_slice($ranking, 0, self::TARGET_RANKING_PAGE_SIZE),
            'rankingTotal' => count($ranking),
            'threatened' => $threatened,
            'maxEnemy' => EnemyTeam::MAX,
            'frontline' => EnemyTeam::FRONTLINE,
        ]);
    }
}
