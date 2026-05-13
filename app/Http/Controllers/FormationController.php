<?php

namespace App\Http\Controllers;

use App\Models\Beast;
use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroFeature;
use App\Models\HeroFurnitureAbility;
use App\Models\HeroRating;
use App\Models\HeroSynergy;
use App\Models\Tier;
use App\Models\UserHero;
use App\Services\Formations\FormationVersioning;
use App\Services\Optimization\ChallengeTypeMode;
use App\Services\Optimization\MultiTeamPartitioner;
use App\Services\Optimization\RecommendedFormationMatcher;
use App\Services\Optimization\RosterStageInferrer;
use App\Services\Optimization\ScoringContext;
use App\Services\Optimization\ScoringProfile;
use App\Services\Optimization\SingleTeamOptimizer;
use App\Services\Optimization\TeamScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FormationController extends Controller
{
    public function index(Request $request): Response
    {
        $formations = $request->user()
            ->formations()
            ->with('challengeType:id,code,name_pt,min_teams,max_teams,is_pvp')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'mode', 'teams_count', 'total_score', 'challenge_type_id', 'updated_at']);

        return Inertia::render('formations/index', [
            'formations' => $formations,
        ]);
    }

    public function create(): Response
    {
        $challengeTypes = ChallengeType::orderBy('display_order')
            ->get(['id', 'code', 'name_pt', 'description', 'min_teams', 'max_teams', 'is_pvp', 'teams_must_be_unique']);

        return Inertia::render('formations/create', [
            'challengeTypes' => $challengeTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'challenge_type_id' => ['required', 'integer', Rule::exists('challenge_types', 'id')],
            'mode' => ['required', 'string', Rule::in(['real', 'target'])],
            'teams_count' => ['nullable', 'integer', 'min:1', 'max:7'],
        ]);

        $challengeType = ChallengeType::findOrFail($data['challenge_type_id']);

        $teamsCount = $data['teams_count'] ?? $challengeType->max_teams;
        $teamsCount = max($challengeType->min_teams, min($challengeType->max_teams, $teamsCount));

        $formation = DB::transaction(function () use ($request, $data, $teamsCount) {
            $formation = $request->user()->formations()->create([
                'challenge_type_id' => $data['challenge_type_id'],
                'name' => $data['name'],
                'mode' => $data['mode'],
                'teams_count' => $teamsCount,
            ]);

            for ($pos = 1; $pos <= $teamsCount; $pos++) {
                $formation->teams()->create(['position' => $pos]);
            }

            return $formation;
        });

        return redirect()->route('formations.edit', $formation);
    }

    public function edit(Request $request, Formation $formation): Response
    {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $formation->load([
            'challengeType:id,code,name_pt,description,min_teams,max_teams,is_pvp,teams_must_be_unique',
            'teams.teamHeroes:id,formation_team_id,hero_id,slot,row',
            'beast:id,slug,name,strength_buff,intelligence_buff,agility_buff,portrait_url',
        ]);

        $heroPool = $this->buildHeroPool($formation, $request->user()->id);

        $beasts = Beast::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'strength_buff', 'intelligence_buff', 'agility_buff', 'portrait_url']);

        // P4 Fase 24: matches com comps recomendados, por time da formation
        $matchesByTeam = $this->buildRecommendedMatches($formation);

        return Inertia::render('formations/edit', [
            'formation' => $formation,
            'heroPool' => $heroPool['heroes'],
            'rosterByHeroId' => $heroPool['roster'],
            'beasts' => $beasts,
            'recommendedMatches' => $matchesByTeam,
            'factions' => Faction::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']),
            'heroClasses' => HeroClass::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'display_order']),
            'tiers' => Tier::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']),
        ]);
    }

    /**
     * Calcula os top matches de comps recomendados por team da formation.
     *
     * @return array<int, array<int, array<string, mixed>>> position => matches[]
     */
    private function buildRecommendedMatches(Formation $formation): array
    {
        $mode = ChallengeTypeMode::forCode($formation->challengeType->code ?? null);

        $allHeroIds = $formation->teams->flatMap(fn ($t) => $t->teamHeroes->pluck('hero_id'))->unique()->values()->all();
        if (empty($allHeroIds)) {
            return [];
        }

        $heroesById = Hero::whereIn('id', $allHeroIds)->get(['id', 'slug', 'name'])->keyBy('id');
        $matcher = new RecommendedFormationMatcher;

        $matchesByTeam = [];
        foreach ($formation->teams as $team) {
            $heroes = $team->teamHeroes
                ->map(fn ($th) => $heroesById->get($th->hero_id))
                ->filter()
                ->values()
                ->all();
            $matchesByTeam[$team->position] = $matcher->match($heroes, $mode);
        }

        return $matchesByTeam;
    }

    public function update(
        Request $request,
        Formation $formation,
        FormationVersioning $versioning,
    ): RedirectResponse {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $challengeType = $formation->challengeType;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'beast_id' => ['nullable', 'integer', Rule::exists('beasts', 'id')->where('is_active', true)],
            'teams' => ['required', 'array', 'min:'.$formation->teams_count, 'max:'.$formation->teams_count],
            'teams.*.position' => ['required', 'integer', 'min:1', 'max:'.$formation->teams_count],
            'teams.*.name' => ['nullable', 'string', 'max:60'],
            'teams.*.heroes' => ['array', 'max:5'],
            'teams.*.heroes.*.slot' => ['required', 'integer', 'min:1', 'max:5'],
            'teams.*.heroes.*.hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')->where('is_active', true)],
            'teams.*.heroes.*.row' => ['nullable', 'string', Rule::in(['front', 'back'])],
        ]);

        if ($challengeType->teams_must_be_unique) {
            $allHeroIds = [];
            foreach ($data['teams'] as $teamIndex => $team) {
                foreach ($team['heroes'] as $h) {
                    if (in_array($h['hero_id'], $allHeroIds, true)) {
                        return back()->withErrors([
                            'teams' => "Este desafio exige equipes únicas: o herói #{$h['hero_id']} aparece em mais de uma equipe.",
                        ]);
                    }
                    $allHeroIds[] = $h['hero_id'];
                }
            }
        }

        if ($formation->mode === 'real') {
            $userHeroIds = $request->user()->userHeroes()->pluck('hero_id')->all();
            foreach ($data['teams'] as $team) {
                foreach ($team['heroes'] as $h) {
                    if (! in_array($h['hero_id'], $userHeroIds, true)) {
                        return back()->withErrors([
                            'teams' => "Modo 'real': o herói #{$h['hero_id']} não está no seu roster.",
                        ]);
                    }
                }
            }
        }

        $versioning->snapshot($formation);

        DB::transaction(function () use ($formation, $data) {
            $formation->update([
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
                'beast_id' => $data['beast_id'] ?? null,
            ]);

            $teamsByPosition = $formation->teams()->get()->keyBy('position');

            foreach ($data['teams'] as $teamPayload) {
                $team = $teamsByPosition[$teamPayload['position']] ?? null;
                if (! $team) {
                    continue;
                }

                $team->update(['name' => $teamPayload['name'] ?? null]);

                $team->teamHeroes()->delete();

                foreach ($teamPayload['heroes'] as $h) {
                    $team->teamHeroes()->create([
                        'hero_id' => $h['hero_id'],
                        'slot' => $h['slot'],
                        'row' => $h['row'] ?? null,
                    ]);
                }
            }
        });

        return back();
    }

    public function suggestTeam(
        Request $request,
        Formation $formation,
        int $position,
        SingleTeamOptimizer $optimizer,
    ): JsonResponse {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $formation->load([
            'challengeType:id,code,scoring_profile,teams_must_be_unique',
            'beast:id,strength_buff,intelligence_buff,agility_buff,tier',
        ]);

        $teamsCount = $formation->teams_count;
        if ($position < 1 || $position > $teamsCount) {
            abort(404);
        }

        $data = $request->validate([
            'teams' => ['required', 'array', 'min:'.$teamsCount, 'max:'.$teamsCount],
            'teams.*.position' => ['required', 'integer', 'min:1', 'max:'.$teamsCount],
            'teams.*.heroes' => ['array', 'max:5'],
            'teams.*.heroes.*.slot' => ['required', 'integer', 'min:1', 'max:5'],
            'teams.*.heroes.*.hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')->where('is_active', true)],
        ]);

        $targetTeam = collect($data['teams'])->firstWhere('position', $position);
        if ($targetTeam === null) {
            abort(404);
        }

        $lockedSlots = collect($targetTeam['heroes'] ?? [])
            ->keyBy('slot')
            ->all();
        $lockedHeroIds = array_map(fn ($h) => (int) $h['hero_id'], $lockedSlots);

        $blockedHeroIds = [];
        if ($formation->challengeType->teams_must_be_unique) {
            foreach ($data['teams'] as $team) {
                if ((int) $team['position'] === $position) {
                    continue;
                }
                foreach ($team['heroes'] ?? [] as $h) {
                    $blockedHeroIds[] = (int) $h['hero_id'];
                }
            }
        }

        $heroPoolById = $this->loadHeroPool($formation, $request->user()->id);
        $userHeroesById = $this->loadUserHeroes($request->user()->id);
        $synergyWeights = $this->loadSynergyWeights();
        $profile = ScoringProfile::fromArray($formation->challengeType->scoring_profile);
        $ctx = $this->loadScoringContext($formation, $heroPoolById, $userHeroesById);

        $result = $optimizer->optimize(
            $heroPoolById,
            $userHeroesById,
            $synergyWeights,
            $profile,
            array_values($lockedHeroIds),
            $blockedHeroIds,
            $formation->beast,
            $ctx,
        );

        $suggestedIds = $result['hero_ids'];
        $newIds = array_values(array_diff($suggestedIds, $lockedHeroIds));

        $emptySlots = [];
        for ($s = 1; $s <= 5; $s++) {
            if (! isset($lockedSlots[$s])) {
                $emptySlots[] = $s;
            }
        }

        $additions = [];
        foreach ($newIds as $idx => $heroId) {
            if (! isset($emptySlots[$idx])) {
                break;
            }
            $additions[] = [
                'slot' => $emptySlots[$idx],
                'hero_id' => $heroId,
            ];
        }

        return response()->json([
            'additions' => $additions,
            'score' => $result['score']->toArray(),
        ]);
    }

    public function suggestAll(
        Request $request,
        Formation $formation,
        MultiTeamPartitioner $partitioner,
    ): JsonResponse {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $formation->load([
            'challengeType:id,code,scoring_profile,teams_must_be_unique',
            'beast:id,strength_buff,intelligence_buff,agility_buff,tier',
        ]);

        $teamsCount = $formation->teams_count;

        $data = $request->validate([
            'teams' => ['required', 'array', 'min:'.$teamsCount, 'max:'.$teamsCount],
            'teams.*.position' => ['required', 'integer', 'min:1', 'max:'.$teamsCount],
            'teams.*.heroes' => ['array', 'max:5'],
            'teams.*.heroes.*.slot' => ['required', 'integer', 'min:1', 'max:5'],
            'teams.*.heroes.*.hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')->where('is_active', true)],
        ]);

        $lockedSlotsByTeam = [];
        $lockedByTeam = [];
        foreach ($data['teams'] as $team) {
            $pos = (int) $team['position'];
            $slots = [];
            $ids = [];
            foreach ($team['heroes'] ?? [] as $h) {
                $slots[(int) $h['slot']] = (int) $h['hero_id'];
                $ids[] = (int) $h['hero_id'];
            }
            $lockedSlotsByTeam[$pos] = $slots;
            $lockedByTeam[$pos] = $ids;
        }

        $heroPoolById = $this->loadHeroPool($formation, $request->user()->id);
        $userHeroesById = $this->loadUserHeroes($request->user()->id);
        $synergyWeights = $this->loadSynergyWeights();
        $profile = ScoringProfile::fromArray($formation->challengeType->scoring_profile);
        $ctx = $this->loadScoringContext($formation, $heroPoolById, $userHeroesById);

        $result = $partitioner->optimize(
            $heroPoolById,
            $userHeroesById,
            $synergyWeights,
            $profile,
            $lockedByTeam,
            $teamsCount,
            (bool) $formation->challengeType->teams_must_be_unique,
            $formation->beast,
            $ctx,
        );

        $teamsResponse = [];
        foreach ($result['teams'] as $pos => $heroIds) {
            $lockedSlots = $lockedSlotsByTeam[$pos] ?? [];
            $lockedIds = $lockedByTeam[$pos] ?? [];
            $newIds = array_values(array_diff($heroIds, $lockedIds));

            $emptySlots = [];
            for ($s = 1; $s <= 5; $s++) {
                if (! isset($lockedSlots[$s])) {
                    $emptySlots[] = $s;
                }
            }

            $additions = [];
            foreach ($newIds as $idx => $heroId) {
                if (! isset($emptySlots[$idx])) {
                    break;
                }
                $additions[] = [
                    'slot' => $emptySlots[$idx],
                    'hero_id' => $heroId,
                ];
            }

            $teamsResponse[] = [
                'position' => $pos,
                'additions' => $additions,
            ];
        }

        return response()->json([
            'teams' => $teamsResponse,
            'total_score' => round($result['total_score'], 3),
        ]);
    }

    public function destroy(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $formation->delete();

        return redirect()->route('formations.index');
    }

    /**
     * @return array<int, Hero>
     */
    private function loadHeroPool(Formation $formation, int $userId): array
    {
        $query = Hero::query()
            ->with(['faction:id,code', 'heroClass:id,code', 'tier:id,code', 'stats'])
            ->where('is_active', true)
            ->where('pending_review', false);

        if ($formation->mode === 'real') {
            $rosterIds = UserHero::where('user_id', $userId)->pluck('hero_id')->all();
            $query->whereIn('id', $rosterIds);
        }

        return $query->get()->keyBy('id')->all();
    }

    /**
     * @return array<int, UserHero>
     */
    private function loadUserHeroes(int $userId): array
    {
        return UserHero::where('user_id', $userId)
            ->with([
                'artifact:id,attributes',
                'collection:id,hp_bonus,attack_bonus,defense_bonus',
            ])
            ->get()
            ->keyBy('hero_id')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function loadSynergyWeights(): array
    {
        $map = [];
        foreach (HeroSynergy::all(['hero_a_id', 'hero_b_id', 'weight']) as $s) {
            $map[TeamScorer::pairKey($s->hero_a_id, $s->hero_b_id)] = $s->weight;
        }

        return $map;
    }

    /**
     * Constrói o ScoringContext (P1+P2 da Fase 24): pré-carrega ratings e features
     * dos heróis do pool, infere stage do roster do user e mapeia challenge_type → mode.
     *
     * @param  array<int, Hero>  $heroPoolById
     * @param  array<int, UserHero>  $userHeroesById
     */
    private function loadScoringContext(Formation $formation, array $heroPoolById, array $userHeroesById): ScoringContext
    {
        $heroIds = array_keys($heroPoolById);
        if (empty($heroIds)) {
            return new ScoringContext;
        }

        $ratingsByHeroId = HeroRating::whereIn('hero_id', $heroIds)
            ->get(['id', 'hero_id', 'mode', 'stage', 'tier', 'source'])
            ->groupBy('hero_id')
            ->all();

        $featuresByHeroId = HeroFeature::whereIn('hero_id', $heroIds)
            ->get(['id', 'hero_id', 'tag'])
            ->groupBy('hero_id')
            ->all();

        $furnitureAbilitiesByHeroId = HeroFurnitureAbility::whereIn('hero_id', $heroIds)
            ->whereNotNull('effects')
            ->get(['id', 'hero_id', 'effects'])
            ->keyBy('hero_id')
            ->all();

        $mode = ChallengeTypeMode::forCode($formation->challengeType->code ?? null);
        $stage = (new RosterStageInferrer)->inferFromRoster($userHeroesById);

        return new ScoringContext(
            ratingsByHeroId: $ratingsByHeroId,
            featuresByHeroId: $featuresByHeroId,
            furnitureAbilitiesByHeroId: $furnitureAbilitiesByHeroId,
            mode: $mode,
            stage: $stage,
        );
    }

    /**
     * @return array{heroes: array<int, array<string, mixed>>, roster: array<int, int>}
     */
    private function buildHeroPool(Formation $formation, int $userId): array
    {
        $rosterByHeroId = UserHero::where('user_id', $userId)
            ->pluck('id', 'hero_id')
            ->all();

        $query = Hero::query()
            ->where('is_active', true)
            ->where('pending_review', false);

        if ($formation->mode === 'real') {
            $query->whereIn('id', array_keys($rosterByHeroId));
        }

        $heroes = $query
            ->orderBy('name')
            ->get([
                'id', 'slug', 'name', 'title',
                'faction_id', 'hero_class_id', 'tier_id',
                'gender', 'role_label', 'portrait_url',
            ])
            ->map(function (Hero $hero) {
                return array_merge($hero->toArray(), [
                    'is_awakened' => Str::slug($hero->name) !== $hero->slug,
                ]);
            })
            ->all();

        return [
            'heroes' => $heroes,
            'roster' => $rosterByHeroId,
        ];
    }
}
