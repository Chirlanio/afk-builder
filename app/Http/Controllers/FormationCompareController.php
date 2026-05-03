<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroSynergy;
use App\Models\Tier;
use App\Models\UserHero;
use App\Services\Optimization\PowerCalculator;
use App\Services\Optimization\ScoringProfile;
use App\Services\Optimization\TeamScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FormationCompareController extends Controller
{
    public function show(
        Request $request,
        Formation $a,
        Formation $b,
        TeamScorer $scorer,
    ): Response {
        $userId = $request->user()->id;
        abort_unless($a->user_id === $userId && $b->user_id === $userId, 403);

        $a->load(['challengeType', 'teams.teamHeroes', 'beast:id,strength_buff,intelligence_buff,agility_buff']);
        $b->load(['challengeType', 'teams.teamHeroes', 'beast:id,strength_buff,intelligence_buff,agility_buff']);

        $allHeroIds = collect([$a, $b])
            ->flatMap(fn ($f) => $f->teams->flatMap(fn ($t) => $t->teamHeroes->pluck('hero_id')))
            ->unique()
            ->values();

        $heroesById = Hero::query()
            ->whereIn('id', $allHeroIds)
            ->with(['faction:id,code', 'heroClass:id,code', 'tier:id,code', 'stats'])
            ->get()
            ->keyBy('id');

        $userHeroesById = UserHero::where('user_id', $userId)
            ->whereIn('hero_id', $allHeroIds)
            ->with([
                'artifact:id,attributes',
                'collection:id,hp_bonus,attack_bonus,defense_bonus',
            ])
            ->get()
            ->keyBy('hero_id')
            ->all();

        $synergyWeights = [];
        foreach (HeroSynergy::all(['hero_a_id', 'hero_b_id', 'weight']) as $s) {
            $synergyWeights[TeamScorer::pairKey($s->hero_a_id, $s->hero_b_id)] = $s->weight;
        }

        $heroesPayload = $heroesById->map(fn (Hero $h) => array_merge($h->toArray(), [
            'is_awakened' => Str::slug($h->name) !== $h->slug,
        ]))->values()->all();

        return Inertia::render('formations/compare', [
            'formationA' => $this->buildFormationPayload($a, $heroesById, $userHeroesById, $synergyWeights, $scorer),
            'formationB' => $this->buildFormationPayload($b, $heroesById, $userHeroesById, $synergyWeights, $scorer),
            'heroes' => $heroesPayload,
            'factions' => Faction::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']),
            'heroClasses' => HeroClass::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'display_order']),
            'tiers' => Tier::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Hero>  $heroesById
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<string, int>  $synergyWeights
     * @return array<string, mixed>
     */
    private function buildFormationPayload(
        Formation $formation,
        $heroesById,
        array $userHeroesById,
        array $synergyWeights,
        TeamScorer $scorer,
    ): array {
        $profile = ScoringProfile::fromArray($formation->challengeType->scoring_profile);

        $teams = [];
        $totalScore = 0.0;
        foreach ($formation->teams as $team) {
            $heroIds = $team->teamHeroes->sortBy('slot')->pluck('hero_id')->all();
            $heroes = collect($heroIds)
                ->map(fn ($id) => $heroesById->get($id))
                ->filter()
                ->all();

            $score = $scorer->score($heroes, $userHeroesById, $synergyWeights, $profile, $formation->beast);
            $totalScore += $score->total;

            $teams[] = [
                'id' => $team->id,
                'position' => $team->position,
                'name' => $team->name,
                'hero_slots' => $team->teamHeroes->sortBy('slot')->map(fn ($th) => [
                    'slot' => $th->slot,
                    'hero_id' => $th->hero_id,
                ])->values()->all(),
                'score' => $score->toArray(),
            ];
        }

        return [
            'id' => $formation->id,
            'name' => $formation->name,
            'mode' => $formation->mode,
            'teams_count' => $formation->teams_count,
            'challenge_type' => [
                'name_pt' => $formation->challengeType->name_pt,
                'is_pvp' => $formation->challengeType->is_pvp,
                'teams_must_be_unique' => $formation->challengeType->teams_must_be_unique,
            ],
            'teams' => $teams,
            'total_score' => round($totalScore, 3),
        ];
    }
}
