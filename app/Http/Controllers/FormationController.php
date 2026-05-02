<?php

namespace App\Http\Controllers;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\UserHero;
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
        ]);

        $heroPool = $this->buildHeroPool($formation, $request->user()->id);

        return Inertia::render('formations/edit', [
            'formation' => $formation,
            'heroPool' => $heroPool['heroes'],
            'rosterByHeroId' => $heroPool['roster'],
            'factions' => Faction::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']),
            'heroClasses' => HeroClass::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'display_order']),
            'tiers' => Tier::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']),
        ]);
    }

    public function update(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $challengeType = $formation->challengeType;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
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

        DB::transaction(function () use ($formation, $data) {
            $formation->update([
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
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

    public function destroy(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $formation->delete();

        return redirect()->route('formations.index');
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
