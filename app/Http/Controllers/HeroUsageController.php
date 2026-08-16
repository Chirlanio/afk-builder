<?php

namespace App\Http\Controllers;

use App\Models\FormationTeamHero;
use App\Models\Hero;
use App\Models\UserHero;
use App\Services\ActiveGameAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HeroUsageController extends Controller
{
    public const TOP_USED_LIMIT = 10;

    public const INVESTED_FORGOTTEN_LIMIT = 10;

    public const NEVER_USED_LIMIT = 50;

    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(Request $request): Response
    {
        $account = $this->active->get();
        $accountId = $account->id;

        // Heroes em formações da conta ativa — base para todas as queries de uso.
        $usedHeroCounts = FormationTeamHero::query()
            ->join('formation_teams', 'formation_team_heroes.formation_team_id', '=', 'formation_teams.id')
            ->join('formations', 'formation_teams.formation_id', '=', 'formations.id')
            ->where('formations.game_account_id', $accountId)
            ->groupBy('formation_team_heroes.hero_id')
            ->select(
                'formation_team_heroes.hero_id',
                DB::raw('COUNT(*) as team_count'),
                DB::raw('COUNT(DISTINCT formations.id) as formation_count'),
                DB::raw('COUNT(DISTINCT formations.challenge_type_id) as challenge_types_count'),
            )
            ->get()
            ->keyBy('hero_id');

        $usedHeroIds = $usedHeroCounts->keys()->all();

        // Top 10 mais usados (por team_count, desempate por formation_count)
        $topUsedIds = $usedHeroCounts
            ->sortByDesc(fn ($row) => [$row->team_count, $row->formation_count])
            ->take(self::TOP_USED_LIMIT)
            ->keys()
            ->all();

        $topUsed = $this->loadHeroesWithCatalog($topUsedIds)
            ->map(fn (Hero $hero) => array_merge($this->heroPayload($hero), [
                'team_count' => (int) $usedHeroCounts[$hero->id]->team_count,
                'formation_count' => (int) $usedHeroCounts[$hero->id]->formation_count,
                'challenge_types_count' => (int) $usedHeroCounts[$hero->id]->challenge_types_count,
            ]))
            ->sortByDesc('team_count')
            ->values();

        // Roster da conta ativa
        $rosterUserHeroes = UserHero::query()
            ->where('game_account_id', $accountId)
            ->get(['hero_id', 'level', 'signature_item', 'furniture', 'engraving']);
        $rosterByHeroId = $rosterUserHeroes->keyBy('hero_id');
        $rosterHeroIds = $rosterUserHeroes->pluck('hero_id')->all();

        // Heroes no roster que nunca foram usados em formação
        $neverUsedIds = array_values(array_diff($rosterHeroIds, $usedHeroIds));
        $neverUsedTotal = count($neverUsedIds);
        $neverUsed = $this->loadHeroesWithCatalog(array_slice($neverUsedIds, 0, self::NEVER_USED_LIMIT))
            ->map(fn (Hero $hero) => array_merge($this->heroPayload($hero), [
                'level' => $rosterByHeroId[$hero->id]->level,
                'signature_item' => $rosterByHeroId[$hero->id]->signature_item,
                'furniture' => $rosterByHeroId[$hero->id]->furniture,
                'engraving' => $rosterByHeroId[$hero->id]->engraving,
            ]))
            ->values();

        // Investidos mas esquecidos: roster com investimento alto (SI≥20 OR F≥3 OR Eng≥60) e zero uso
        $investedForgottenCollection = $rosterUserHeroes
            ->filter(fn ($uh) => ! in_array($uh->hero_id, $usedHeroIds, true)
                && ($uh->signature_item >= 20 || $uh->furniture >= 3 || $uh->engraving >= 60));
        // Score interno pra ordenar: SI ponderado mais alto que furniture, depois engraving.
        $investedForgottenSorted = $investedForgottenCollection
            ->sortByDesc(fn ($uh) => $uh->signature_item * 10 + $uh->furniture * 50 + $uh->engraving)
            ->take(self::INVESTED_FORGOTTEN_LIMIT);

        $investedForgotten = $this->loadHeroesWithCatalog($investedForgottenSorted->pluck('hero_id')->all())
            ->map(fn (Hero $hero) => array_merge($this->heroPayload($hero), [
                'level' => $rosterByHeroId[$hero->id]->level,
                'signature_item' => $rosterByHeroId[$hero->id]->signature_item,
                'furniture' => $rosterByHeroId[$hero->id]->furniture,
                'engraving' => $rosterByHeroId[$hero->id]->engraving,
            ]))
            ->sortByDesc(fn ($row) => $row['signature_item'] * 10 + $row['furniture'] * 50 + $row['engraving'])
            ->values();

        // Uso por challenge type — top 5 heroes por cada challenge type que a conta usou
        $usageByChallengeType = $this->computeUsageByChallengeType($accountId);

        return Inertia::render('heroes/usage', [
            'stats' => [
                'total_used' => $usedHeroCounts->count(),
                'total_in_roster' => count($rosterHeroIds),
                'never_used_total' => $neverUsedTotal,
                'invested_forgotten_total' => $investedForgottenCollection->count(),
            ],
            'topUsed' => $topUsed,
            'neverUsed' => $neverUsed,
            'neverUsedShownLimit' => self::NEVER_USED_LIMIT,
            'investedForgotten' => $investedForgotten,
            'usageByChallengeType' => $usageByChallengeType,
        ]);
    }

    /**
     * Para cada challenge type usado pelo user, retorna top 5 heroes (por team_count).
     *
     * @return array<int, array{code: string, name_pt: string, heroes: array<int, array<string, mixed>>}>
     */
    private function computeUsageByChallengeType(int $accountId): array
    {
        $rows = FormationTeamHero::query()
            ->join('formation_teams', 'formation_team_heroes.formation_team_id', '=', 'formation_teams.id')
            ->join('formations', 'formation_teams.formation_id', '=', 'formations.id')
            ->join('challenge_types', 'formations.challenge_type_id', '=', 'challenge_types.id')
            ->where('formations.game_account_id', $accountId)
            ->groupBy(
                'formation_team_heroes.hero_id',
                'challenge_types.code',
                'challenge_types.name_pt',
                'challenge_types.display_order',
            )
            ->select(
                'formation_team_heroes.hero_id',
                'challenge_types.code',
                'challenge_types.name_pt',
                'challenge_types.display_order',
                DB::raw('COUNT(*) as team_count'),
            )
            ->orderBy('challenge_types.display_order')
            ->orderByDesc('team_count')
            ->get();

        $grouped = $rows->groupBy('code');
        $allHeroIds = $rows->pluck('hero_id')->unique()->all();
        $heroesById = $this->loadHeroesWithCatalog($allHeroIds)->keyBy('id');

        $result = [];
        foreach ($grouped as $code => $bucket) {
            $first = $bucket->first();
            $heroes = $bucket
                ->take(5)
                ->map(function ($row) use ($heroesById) {
                    $hero = $heroesById->get($row->hero_id);
                    if (! $hero) {
                        return null;
                    }

                    return array_merge($this->heroPayload($hero), [
                        'team_count' => (int) $row->team_count,
                    ]);
                })
                ->filter()
                ->values()
                ->all();

            $result[] = [
                'code' => $code,
                'name_pt' => $first->name_pt,
                'heroes' => $heroes,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function loadHeroesWithCatalog(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return Hero::query()
            ->whereIn('id', $ids)
            ->with([
                'faction:id,code,name_pt',
                'heroClass:id,code,name_pt',
                'tier:id,code,name_pt',
            ])
            ->get(['id', 'slug', 'name', 'portrait_url', 'faction_id', 'hero_class_id', 'tier_id'])
            ->keyBy('id');
    }

    private function heroPayload(Hero $hero): array
    {
        return [
            'id' => $hero->id,
            'slug' => $hero->slug,
            'name' => $hero->name,
            'portrait_url' => $hero->portrait_url,
            'faction_code' => $hero->faction?->code,
            'class_code' => $hero->heroClass?->code,
            'tier_code' => $hero->tier?->code,
        ];
    }
}
