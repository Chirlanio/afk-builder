<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroSynergy;
use App\Models\Tier;
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

    public function show(Request $request, string $slug): Response
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
            ])
            ->firstOrFail();

        $heroPayload = array_merge($hero->toArray(), [
            'is_awakened' => Str::slug($hero->name) !== $hero->slug,
        ]);

        $userHero = $request->user()
            ->userHeroes()
            ->where('hero_id', $hero->id)
            ->first([
                'id', 'hero_id', 'level', 'ascension_code', 'ascension_value',
                'signature_item', 'furniture', 'engraving', 'notes',
            ]);

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

        return Inertia::render('heroes/show', [
            'hero' => $heroPayload,
            'userHero' => $userHero,
            'synergies' => $synergies,
            'factions' => Faction::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']),
            'heroClasses' => HeroClass::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'display_order']),
            'tiers' => Tier::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']),
        ]);
    }
}
