<?php

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Collection;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroSynergy;
use App\Models\Tier;
use App\Services\Optimization\PowerCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HeroController extends Controller
{
    public function index(Request $request): Response
    {
        $heroes = Hero::query()
            ->where('is_active', true)
            ->where('pending_review', false)
            ->orderBy('name')
            ->get([
                'id', 'slug', 'name', 'title',
                'faction_id', 'hero_class_id', 'tier_id',
                'gender', 'type', 'role_label', 'portrait_url',
            ])
            ->map(function (Hero $hero) {
                return array_merge($hero->toArray(), [
                    'is_awakened' => Str::slug($hero->name) !== $hero->slug,
                ]);
            });

        $factions = Faction::orderBy('display_order')
            ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']);

        $heroClasses = HeroClass::orderBy('display_order')
            ->get(['id', 'code', 'name', 'name_pt', 'display_order']);

        $tiers = Tier::orderBy('display_order')
            ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']);

        $roster = $request->user()
            ->userHeroes()
            ->get([
                'id', 'hero_id', 'level', 'ascension_code', 'ascension_value',
                'signature_item', 'furniture', 'engraving', 'notes',
            ])
            ->keyBy('hero_id');

        return Inertia::render('heroes/index', [
            'heroes' => $heroes,
            'factions' => $factions,
            'heroClasses' => $heroClasses,
            'tiers' => $tiers,
            'roster' => $roster,
        ]);
    }

    /**
     * Permalink legacy: redireciona para /heroes?open={slug} que abre a modal unificada no índice.
     */
    public function show(string $slug): RedirectResponse
    {
        // Mantém URL antiga funcionando — o detalhe completo agora é exibido em modal no índice.
        return redirect()->route('heroes.index', ['open' => $slug]);
    }

    /**
     * Retorna JSON com os dados completos do herói para a HeroDetailModal carregar via axios.
     */
    public function details(Request $request, string $slug, PowerCalculator $power): JsonResponse
    {
        $hero = Hero::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'faction:id,code,name_pt,color_hex',
                'heroClass:id,code,name_pt',
                'tier:id,code,name_pt,max_level',
                'abilities' => fn ($q) => $q->orderBy('display_order'),
                'furnitureAbility',
                'stats',
            ])
            ->firstOrFail();

        $heroPayload = array_merge($hero->toArray(), [
            'is_awakened' => Str::slug($hero->name) !== $hero->slug,
        ]);

        $userHero = $request->user()
            ->userHeroes()
            ->with([
                'artifact:id,slug,name,kind,hero_class_id,description,portrait_url,max_stars',
                'collection:id,slug,name,description,hp_bonus,attack_bonus,defense_bonus,portrait_url',
            ])
            ->where('hero_id', $hero->id)
            ->first([
                'id', 'hero_id', 'level', 'ascension_code', 'ascension_value',
                'signature_item', 'furniture', 'engraving',
                'artifact_id', 'artifact_star_level', 'collection_id', 'notes',
            ]);

        $userHeroPayload = null;
        if ($userHero) {
            $userHeroPayload = $userHero->toArray();
            $userHeroPayload['power_rating'] = (int) round($power->forUserHero($userHero, $hero));
        }

        $availableCollections = Collection::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'description', 'hp_bonus', 'attack_bonus', 'defense_bonus', 'portrait_url']);

        $availableArtifacts = Artifact::query()
            ->where('is_active', true)
            ->where(function ($q) use ($hero) {
                $q->where('kind', Artifact::KIND_DURA)
                  ->orWhere(function ($q2) use ($hero) {
                      $q2->where('kind', Artifact::KIND_CLASS)
                         ->where('hero_class_id', $hero->hero_class_id);
                  });
            })
            ->orderByRaw("CASE kind WHEN 'dura' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'kind', 'hero_class_id', 'description', 'attributes', 'powers', 'obtained_in', 'portrait_url', 'max_stars']);

        $synergyPartnerIds = HeroSynergy::query()
            ->where('hero_a_id', $hero->id)
            ->orWhere('hero_b_id', $hero->id)
            ->get(['hero_a_id', 'hero_b_id', 'weight', 'note'])
            ->map(fn ($s) => [
                'hero_id' => $s->hero_a_id === $hero->id ? $s->hero_b_id : $s->hero_a_id,
                'weight' => $s->weight,
                'note' => $s->note,
            ]);

        $partnerHeroes = Hero::query()
            ->whereIn('id', $synergyPartnerIds->pluck('hero_id'))
            ->where('is_active', true)
            ->with(['faction:id,code', 'heroClass:id,code', 'tier:id,code'])
            ->get(['id', 'slug', 'name', 'faction_id', 'hero_class_id', 'tier_id', 'portrait_url'])
            ->keyBy('id');

        $synergies = $synergyPartnerIds
            ->map(function (array $s) use ($partnerHeroes) {
                $partner = $partnerHeroes->get($s['hero_id']);
                if (! $partner) {
                    return null;
                }

                return [
                    'partner' => array_merge($partner->toArray(), [
                        'is_awakened' => Str::slug($partner->name) !== $partner->slug,
                    ]),
                    'weight' => $s['weight'],
                    'note' => $s['note'],
                ];
            })
            ->filter()
            ->sortByDesc('weight')
            ->values();

        return response()->json([
            'hero' => $heroPayload,
            'userHero' => $userHeroPayload,
            'synergies' => $synergies,
            'availableArtifacts' => $availableArtifacts,
            'availableCollections' => $availableCollections,
        ]);
    }
}
