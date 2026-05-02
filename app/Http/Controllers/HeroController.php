<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
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
                'gender', 'role_label', 'portrait_url',
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
}
