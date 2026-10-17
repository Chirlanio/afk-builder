<?php

namespace App\Http\Controllers;

use App\Models\Beast;
use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\FormationTeam;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroFeature;
use App\Models\HeroFurnitureAbility;
use App\Models\HeroRating;
use App\Models\HeroSynergy;
use App\Models\Tier;
use App\Models\UserHero;
use App\Services\ActiveGameAccount;
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
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(Request $request): Response
    {
        $account = $this->active->get();

        // Fase 48: pré-calcula has_beast por formation via subquery agregada — evita N+1
        // sem carregar a relação teams na listagem.
        $formationIdsWithBeast = $account
            ->formations()
            ->whereHas('teams', fn ($q) => $q->whereNotNull('beast_id'))
            ->pluck('id')
            ->all();

        $formations = $account
            ->formations()
            ->with([
                'challengeType:id,code,name_pt,min_teams,max_teams,is_pvp',
                'tags:id,name,color',
            ])
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'name',
                'mode',
                'teams_count',
                'total_score',
                'challenge_type_id',
                'share_token',
                'notes',
                'updated_at',
            ])
            ->map(function (Formation $f) use ($formationIdsWithBeast) {
                // Não enviar o corpo da nota (pode ser grande); só sinalizar presença.
                $hasNotes = ! empty(trim((string) $f->notes));
                $f->makeHidden(['notes']);
                $f->setAttribute('has_notes', $hasNotes);
                $f->setAttribute('has_beast', in_array($f->id, $formationIdsWithBeast, true));

                return $f;
            });

        // Lista de challenge types em uso (pra filter dropdown) — só aqueles com formações
        $availableChallengeTypes = $formations
            ->pluck('challengeType')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($ct) => [
                'id' => $ct->id,
                'code' => $ct->code,
                'name_pt' => $ct->name_pt,
            ]);

        // Fase 35: lista de tags do user pra filtro na index
        $availableTags = $account
            ->formationTags()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return Inertia::render('formations/index', [
            'formations' => $formations,
            'availableChallengeTypes' => $availableChallengeTypes,
            'availableTags' => $availableTags,
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

        // Campanha: a maioria das fases usa 1 equipe (só a 4ª de cada bloco tem
        // vários grupos) — o padrão é o mínimo; nos demais modos, o máximo.
        $defaultTeams = $challengeType->code === 'campaign' ? $challengeType->min_teams : $challengeType->max_teams;
        $teamsCount = $data['teams_count'] ?? $defaultTeams;
        $teamsCount = max($challengeType->min_teams, min($challengeType->max_teams, $teamsCount));

        $account = $this->active->get();

        $formation = DB::transaction(function () use ($account, $data, $teamsCount) {
            $formation = $account->formations()->create([
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

    public function edit(Request $request, Formation $formation, TeamScorer $scorer): Response
    {
        $account = $this->active->get();
        abort_unless($formation->game_account_id === $account->id, 403);

        $formation->load([
            'challengeType:id,code,scoring_profile,name_pt,description,min_teams,max_teams,is_pvp,teams_must_be_unique',
            'teams.teamHeroes:id,formation_team_id,hero_id,slot,row',
            'teams.beast:id,slug,name,buffs_strength,buffs_intelligence,buffs_agility,buffs_team,portrait_url,tier',
            'tags:id,name,color',
        ]);

        $heroPool = $this->buildHeroPool($formation, $account->id);

        $beasts = Beast::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'buffs_strength', 'buffs_intelligence', 'buffs_agility', 'buffs_team', 'portrait_url', 'tier']);

        // P4 Fase 24: matches com comps recomendados, por time da formation
        $matchesByTeam = $this->buildRecommendedMatches($formation);

        // Fase 26.4 + 27: breakdown completo de score por team. Aplica overrides
        // salvos no DB pra que o detalhamento reflita os pesos custom do user.
        $scoresByTeamPosition = $this->computeScoresByPosition($formation, $account->id, $scorer);

        // Fase 26.5: pesos default do challenge_type para o slider tuner do frontend
        $scoringProfileDefaults = $this->extractScoringWeights($formation->challengeType->scoring_profile ?? []);
        // Fase 27: overrides salvos na formation (null quando o user nunca tunou ou resetou)
        $scoringProfileOverrides = $formation->scoring_weights_override;

        // Fase 35: todas as tags do user (pra Popover de seleção no editor)
        $availableTags = $account
            ->formationTags()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        // Fase 30: presets do user — globais (challenge_type_id null) + os do challenge type da formation
        $weightPresets = $account
            ->scoringWeightPresets()
            ->where(function ($query) use ($formation) {
                $query->whereNull('challenge_type_id')
                    ->orWhere('challenge_type_id', $formation->challenge_type_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'challenge_type_id', 'weights']);

        return Inertia::render('formations/edit', [
            'formation' => $formation,
            'heroPool' => $heroPool['heroes'],
            'rosterByHeroId' => $heroPool['roster'],
            'engravingByHeroId' => $heroPool['engravings'],
            'beasts' => $beasts,
            'recommendedMatches' => $matchesByTeam,
            'scoresByTeamPosition' => $scoresByTeamPosition,
            'scoringProfileDefaults' => $scoringProfileDefaults,
            'scoringProfileOverrides' => $scoringProfileOverrides,
            'weightPresets' => $weightPresets,
            'availableTags' => $availableTags,
            'factions' => Faction::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']),
            'heroClasses' => HeroClass::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'display_order']),
            'tiers' => Tier::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']),
        ]);
    }

    /**
     * Extrai apenas os 8 pesos relevantes do scoring_profile (ignora flags
     * como pvp/dps_focus/partition_optimization). Aplica defaults idênticos
     * aos do PHP ScoringProfile constructor para profiles incompletos.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, float>
     */
    private function extractScoringWeights(array $profile): array
    {
        return [
            'faction_bonus_weight' => (float) ($profile['faction_bonus_weight'] ?? 1.0),
            'class_balance_weight' => (float) ($profile['class_balance_weight'] ?? 1.0),
            'synergy_weight' => (float) ($profile['synergy_weight'] ?? 1.0),
            'power_weight' => (float) ($profile['power_weight'] ?? 1.0),
            'tier_rating_weight' => (float) ($profile['tier_rating_weight'] ?? 1.0),
            'feature_weight' => (float) ($profile['feature_weight'] ?? 0.5),
            'furniture_effect_weight' => (float) ($profile['furniture_effect_weight'] ?? 0.4),
            'engraving_weight' => (float) ($profile['engraving_weight'] ?? 0.3),
        ];
    }

    /**
     * Calcula o ScoreBreakdown atual de cada team da formation. Retorna um
     * map position => breakdown.toArray() (ou null para teams vazios).
     *
     * @return array<int, array<string, mixed>|null>
     */
    private function computeScoresByPosition(Formation $formation, int $accountId, TeamScorer $scorer): array
    {
        $scores = [];

        $heroPoolById = $this->loadHeroPool($formation, $accountId);
        $userHeroesById = $this->loadUserHeroes($accountId);
        $synergyWeights = $this->loadSynergyWeights();
        $profile = ScoringProfile::fromArray($formation->challengeType->scoring_profile);
        // Fase 27: aplica overrides persistidos quando presentes — breakdown reflete pesos custom.
        if (! empty($formation->scoring_weights_override)) {
            $profile = $profile->withOverrides($formation->scoring_weights_override);
        }
        $ctx = ! empty($heroPoolById)
            ? $this->loadScoringContext($formation, $heroPoolById, $userHeroesById)
            : new ScoringContext;

        foreach ($formation->teams as $team) {
            $heroIds = $team->teamHeroes->sortBy('slot')->pluck('hero_id')->all();
            if (empty($heroIds)) {
                $scores[$team->position] = null;

                continue;
            }
            $heroes = array_values(array_filter(array_map(fn ($id) => $heroPoolById[$id] ?? null, $heroIds)));
            if (empty($heroes)) {
                $scores[$team->position] = null;

                continue;
            }
            $breakdown = $scorer->score($heroes, $userHeroesById, $synergyWeights, $profile, $team->beast, $ctx, $team->beast_level ?? Beast::MAX_LEVEL);
            $scores[$team->position] = $breakdown->toArray();
        }

        return $scores;
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
        $account = $this->active->get();
        abort_unless($formation->game_account_id === $account->id, 403);

        $challengeType = $formation->challengeType;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('formation_tags', 'id')->where('game_account_id', $account->id),
            ],
            'teams' => ['required', 'array', 'min:'.$formation->teams_count, 'max:'.$formation->teams_count],
            'teams.*.position' => ['required', 'integer', 'min:1', 'max:'.$formation->teams_count],
            'teams.*.name' => ['nullable', 'string', 'max:60'],
            'teams.*.beast_id' => ['nullable', 'integer', Rule::exists('beasts', 'id')->where('is_active', true)],
            'teams.*.beast_level' => ['nullable', 'integer', 'min:1', 'max:'.Beast::MAX_LEVEL],
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
            $userHeroIds = $account->userHeroes()->pluck('hero_id')->all();
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
            ]);

            // Fase 35: sync tags (full-replace, igual ao padrão dos teams)
            if (array_key_exists('tag_ids', $data)) {
                $formation->tags()->sync($data['tag_ids'] ?? []);
            }

            $teamsByPosition = $formation->teams()->get()->keyBy('position');

            foreach ($data['teams'] as $teamPayload) {
                $team = $teamsByPosition[$teamPayload['position']] ?? null;
                if (! $team) {
                    continue;
                }

                $team->update([
                    'name' => $teamPayload['name'] ?? null,
                    'beast_id' => $teamPayload['beast_id'] ?? null,
                    'beast_level' => $teamPayload['beast_level'] ?? Beast::MAX_LEVEL,
                ]);

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
        $account = $this->active->get();
        abort_unless($formation->game_account_id === $account->id, 403);

        $formation->load([
            'challengeType:id,code,scoring_profile,teams_must_be_unique',
            'teams.beast:id,buffs_strength,buffs_intelligence,buffs_agility,buffs_team,tier',
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
            'weight_overrides' => ['nullable', 'array'],
            'weight_overrides.*' => ['numeric', 'min:0', 'max:3'],
        ]);

        $targetTeam = collect($data['teams'])->firstWhere('position', $position);
        if ($targetTeam === null) {
            abort(404);
        }

        $teamModel = $formation->teams->firstWhere('position', $position);
        $teamBeast = $teamModel?->beast;

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

        $heroPoolById = $this->loadHeroPool($formation, $account->id);
        $userHeroesById = $this->loadUserHeroes($account->id);
        $synergyWeights = $this->loadSynergyWeights();
        $profile = ScoringProfile::fromArray($formation->challengeType->scoring_profile);
        // Prioridade: body weight_overrides > formation.scoring_weights_override > defaults.
        $effectiveOverrides = $data['weight_overrides'] ?? $formation->scoring_weights_override ?? null;
        if (! empty($effectiveOverrides)) {
            $profile = $profile->withOverrides($effectiveOverrides);
        }
        $ctx = $this->loadScoringContext($formation, $heroPoolById, $userHeroesById);

        $result = $optimizer->optimize(
            $heroPoolById,
            $userHeroesById,
            $synergyWeights,
            $profile,
            array_values($lockedHeroIds),
            $blockedHeroIds,
            $teamBeast,
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
        $account = $this->active->get();
        abort_unless($formation->game_account_id === $account->id, 403);

        $formation->load([
            'challengeType:id,code,scoring_profile,teams_must_be_unique',
        ]);

        $teamsCount = $formation->teams_count;

        $data = $request->validate([
            'teams' => ['required', 'array', 'min:'.$teamsCount, 'max:'.$teamsCount],
            'teams.*.position' => ['required', 'integer', 'min:1', 'max:'.$teamsCount],
            'teams.*.heroes' => ['array', 'max:5'],
            'teams.*.heroes.*.slot' => ['required', 'integer', 'min:1', 'max:5'],
            'teams.*.heroes.*.hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')->where('is_active', true)],
            'weight_overrides' => ['nullable', 'array'],
            'weight_overrides.*' => ['numeric', 'min:0', 'max:3'],
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

        $heroPoolById = $this->loadHeroPool($formation, $account->id);
        $userHeroesById = $this->loadUserHeroes($account->id);
        $synergyWeights = $this->loadSynergyWeights();
        $profile = ScoringProfile::fromArray($formation->challengeType->scoring_profile);
        // Prioridade: body weight_overrides > formation.scoring_weights_override > defaults.
        $effectiveOverrides = $data['weight_overrides'] ?? $formation->scoring_weights_override ?? null;
        if (! empty($effectiveOverrides)) {
            $profile = $profile->withOverrides($effectiveOverrides);
        }
        $ctx = $this->loadScoringContext($formation, $heroPoolById, $userHeroesById);

        // Fase 48: suggestAll otimiza só distribuição de heróis entre teams. Beasts por
        // team são definidas separadamente (endpoint best-beast por team) — passar null aqui
        // evita a complexidade de avaliar combinações team×beast num único otimizador multi-team.
        $result = $partitioner->optimize(
            $heroPoolById,
            $userHeroesById,
            $synergyWeights,
            $profile,
            $lockedByTeam,
            $teamsCount,
            (bool) $formation->challengeType->teams_must_be_unique,
            null,
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

    /**
     * Persiste o override de pesos do scoring na formation. Aceita objeto vazio
     * (limpa o override e volta a usar o profile do challenge_type). Cada peso
     * deve estar entre 0 e 3 (mesma faixa do slider e do suggest endpoint).
     */
    public function updateScoringWeights(Request $request, Formation $formation): JsonResponse
    {
        abort_unless($formation->game_account_id === $this->active->get()->id, 403);

        $data = $request->validate([
            'weights' => ['nullable', 'array'],
            'weights.*' => ['numeric', 'min:0', 'max:3'],
        ]);

        $weights = $data['weights'] ?? null;
        $formation->update([
            'scoring_weights_override' => empty($weights) ? null : $weights,
        ]);

        return response()->json([
            'scoring_weights_override' => $formation->fresh()->scoring_weights_override,
        ]);
    }

    public function destroy(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($formation->game_account_id === $this->active->get()->id, 403);

        $formation->delete();

        return redirect()->route('formations.index');
    }

    /**
     * Altera quantas equipes a formation tem. Valida contra min_teams/max_teams do challenge_type.
     * Aumentar: cria teams vazios nas novas posições.
     * Diminuir: deleta teams (e team_heroes via cascade FK) das posições removidas — destrutivo.
     */
    public function updateTeamsCount(
        Request $request,
        Formation $formation,
        FormationVersioning $versioning,
    ): RedirectResponse {
        abort_unless($formation->game_account_id === $this->active->get()->id, 403);

        $challengeType = $formation->challengeType;

        $data = $request->validate([
            'teams_count' => [
                'required',
                'integer',
                'min:'.$challengeType->min_teams,
                'max:'.$challengeType->max_teams,
            ],
        ]);

        $current = $formation->teams_count;
        $next = $data['teams_count'];

        if ($next === $current) {
            return back();
        }

        // Snapshot antes de mexer — diminuir é destrutivo, user pode querer restaurar.
        $versioning->snapshot($formation);

        DB::transaction(function () use ($formation, $current, $next) {
            if ($next > $current) {
                // Aumenta: cria teams vazios nas novas posições.
                for ($pos = $current + 1; $pos <= $next; $pos++) {
                    $formation->teams()->create(['position' => $pos]);
                }
            } else {
                // Diminui: remove teams das posições > next (cascade limpa team_heroes).
                $formation->teams()
                    ->where('position', '>', $next)
                    ->get()
                    ->each(fn ($team) => $team->delete());
            }

            $formation->update(['teams_count' => $next]);
        });

        return back();
    }

    /**
     * Calcula a melhor besta pra um team específico baseado nos heróis posicionados.
     * Critério: maior soma de buffs aplicáveis = Σ_heroes (buffs_<type>_da_besta + buffs_team_da_besta).
     * O level do team modula linearmente os buffs (level/MAX_LEVEL).
     * Persiste a escolha no team.beast_id e retorna o beast escolhido.
     */
    public function bestBeast(Request $request, Formation $formation, FormationTeam $team): JsonResponse
    {
        abort_unless($formation->game_account_id === $this->active->get()->id, 403);
        abort_unless($team->formation_id === $formation->id, 404);

        $heroIds = $team->teamHeroes()->pluck('hero_id')->all();
        if (empty($heroIds)) {
            return response()->json([
                'ok' => false,
                'reason' => 'team_empty',
                'message' => 'Adicione heróis ao time antes de escolher a melhor besta.',
            ], 422);
        }

        // Conta heroes por type (strength/intelligence/agility). Heroes sem type contam zero
        // pros buffs por scope, mas ainda recebem buffs de team.
        $typeCounts = Hero::whereIn('id', $heroIds)
            ->whereNotNull('type')
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $totalHeroCount = count($heroIds);

        $beasts = Beast::query()
            ->where('is_active', true)
            ->get(['id', 'slug', 'name', 'buffs_strength', 'buffs_intelligence', 'buffs_agility', 'buffs_team', 'portrait_url', 'tier']);

        if ($beasts->isEmpty()) {
            return response()->json([
                'ok' => false,
                'reason' => 'no_beasts',
                'message' => 'Nenhuma besta ativa cadastrada.',
            ], 422);
        }

        // Detecta cenário: todas as bestas com buffs vazios (admin não preencheu).
        $sumBuffs = function ($scope) {
            return is_array($scope) ? array_sum(array_map('floatval', $scope)) : 0.0;
        };
        $anyBeastHasBuff = $beasts->contains(
            fn ($b) => $sumBuffs($b->buffs_strength) > 0
                || $sumBuffs($b->buffs_intelligence) > 0
                || $sumBuffs($b->buffs_agility) > 0
                || $sumBuffs($b->buffs_team) > 0,
        );
        if (! $anyBeastHasBuff) {
            return response()->json([
                'ok' => false,
                'reason' => 'beasts_without_buffs',
                'message' => 'Bestas cadastradas não têm buffs configurados. Peça a um admin pra preencher em /admin/beasts.',
            ], 422);
        }

        $best = null;
        $bestScore = -1;
        foreach ($beasts as $beast) {
            // Soma buffs por type × count de heroes daquele type
            $score = $sumBuffs($beast->buffs_strength) * ($typeCounts['strength'] ?? 0)
                + $sumBuffs($beast->buffs_intelligence) * ($typeCounts['intelligence'] ?? 0)
                + $sumBuffs($beast->buffs_agility) * ($typeCounts['agility'] ?? 0)
                + $sumBuffs($beast->buffs_team) * $totalHeroCount;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $beast;
            }
        }

        if ($best === null || $bestScore <= 0) {
            return response()->json([
                'ok' => false,
                'reason' => 'no_buff_match',
                'message' => 'Nenhuma besta tem buffs aplicáveis a este time.',
            ], 422);
        }

        $team->update(['beast_id' => $best->id]);

        return response()->json([
            'ok' => true,
            'beast' => $best,
            'score' => round($bestScore, 2),
        ]);
    }

    /**
     * @return array<int, Hero>
     */
    private function loadHeroPool(Formation $formation, int $accountId): array
    {
        $query = Hero::query()
            ->with(['faction:id,code', 'heroClass:id,code', 'tier:id,code', 'stats'])
            ->where('is_active', true)
            ->where('pending_review', false);

        if ($formation->mode === 'real') {
            $rosterIds = UserHero::where('game_account_id', $accountId)->pluck('hero_id')->all();
            $query->whereIn('id', $rosterIds);
        }

        return $query->get()->keyBy('id')->all();
    }

    /**
     * @return array<int, UserHero>
     */
    private function loadUserHeroes(int $accountId): array
    {
        return UserHero::where('game_account_id', $accountId)
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
            challengeCode: $formation->challengeType->code ?? null,
        );
    }

    /**
     * @return array{heroes: array<int, array<string, mixed>>, roster: array<int, int>, engravings: array<int, int>}
     */
    private function buildHeroPool(Formation $formation, int $accountId): array
    {
        $rosterRows = UserHero::where('game_account_id', $accountId)
            ->get(['id', 'hero_id', 'engraving']);

        $rosterByHeroId = [];
        $engravingByHeroId = [];
        foreach ($rosterRows as $uh) {
            $rosterByHeroId[$uh->hero_id] = $uh->id;
            $engravingByHeroId[$uh->hero_id] = (int) $uh->engraving;
        }

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
            'engravings' => $engravingByHeroId,
        ];
    }
}
