<?php

namespace App\Http\Controllers;

use App\Models\ChallengeType;
use App\Models\Hero;
use App\Models\RecommendedFormation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RecommendedFormationController extends Controller
{
    /**
     * Mapeamento de mode (RecommendedFormation) → code de ChallengeType usado
     * ao duplicar para a Formation do usuário. Sem match exato: cai em campaign.
     */
    private const MODE_TO_CHALLENGE_CODE = [
        RecommendedFormation::MODE_PVP => 'arena_of_heroes',
        RecommendedFormation::MODE_PVE => 'campaign',
        RecommendedFormation::MODE_TWISTED_REALM => 'dream_realm',
        RecommendedFormation::MODE_LABYRINTH => 'arcane_labyrinth',
        RecommendedFormation::MODE_KING_TOWER => 'kings_tower',
        RecommendedFormation::MODE_ARCANE_LABYRINTH => 'arcane_labyrinth',
    ];

    public function index(): Response
    {
        $recommended = RecommendedFormation::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $allSlugs = $recommended->flatMap(fn ($r) => $r->hero_slugs ?? [])->unique()->values();

        $heroes = Hero::query()
            ->whereIn('slug', $allSlugs)
            ->where('is_active', true)
            ->with(['faction:id,code,name_pt,color_hex'])
            ->get(['id', 'slug', 'name', 'title', 'faction_id', 'portrait_url', 'thumbnail_url'])
            ->keyBy('slug');

        return Inertia::render('formations/recommended/index', [
            'recommendedFormations' => $recommended->map(fn (RecommendedFormation $rf) => [
                'id' => $rf->id,
                'slug' => $rf->slug,
                'name' => $rf->name,
                'mode' => $rf->mode,
                'description' => $rf->description,
                'hero_slugs' => $rf->hero_slugs,
                'source_url' => $rf->source_url,
                'source_label' => $rf->source_label,
            ]),
            'heroesBySlug' => $heroes,
        ]);
    }

    public function clone(Request $request, RecommendedFormation $recommended): RedirectResponse
    {
        abort_unless($recommended->is_active, 404);

        $challengeCode = self::MODE_TO_CHALLENGE_CODE[$recommended->mode] ?? 'campaign';
        $challengeType = ChallengeType::where('code', $challengeCode)->firstOrFail();

        $heroSlugs = $recommended->hero_slugs ?? [];
        $heroIds = Hero::whereIn('slug', $heroSlugs)
            ->where('is_active', true)
            ->pluck('id', 'slug');

        $formation = DB::transaction(function () use ($request, $recommended, $challengeType, $heroSlugs, $heroIds) {
            $formation = $request->user()->formations()->create([
                'challenge_type_id' => $challengeType->id,
                'name' => $recommended->name.' (cópia)',
                'mode' => 'target',
                'teams_count' => 1,
                'notes' => $recommended->description.($recommended->source_label ? "\n\nFonte: {$recommended->source_label}" : ''),
            ]);

            $team = $formation->teams()->create(['position' => 1]);

            $slot = 1;
            foreach ($heroSlugs as $slug) {
                if (! isset($heroIds[$slug])) {
                    continue;
                }
                if ($slot > 5) {
                    break;
                }
                $team->teamHeroes()->create([
                    'hero_id' => $heroIds[$slug],
                    'slot' => $slot,
                ]);
                $slot++;
            }

            return $formation;
        });

        return redirect()
            ->route('formations.edit', $formation)
            ->with('flash', "Comp '{$recommended->name}' duplicado para suas formações.");
    }
}
