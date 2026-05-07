<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $rosterTotal = $user->userHeroes()->count();

        $rosterAscended = $user->userHeroes()
            ->where('ascension_value', '>=', 10)
            ->count();

        $rosterByFaction = $user->userHeroes()
            ->join('heroes', 'user_heroes.hero_id', '=', 'heroes.id')
            ->join('factions', 'heroes.faction_id', '=', 'factions.id')
            ->groupBy('factions.code', 'factions.name_pt', 'factions.display_order')
            ->orderBy('factions.display_order')
            ->select('factions.code', 'factions.name_pt', DB::raw('COUNT(*) as total'))
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name_pt' => $row->name_pt,
                'total' => (int) $row->total,
            ]);

        $rosterAwakened = $user->userHeroes()
            ->join('heroes', 'user_heroes.hero_id', '=', 'heroes.id')
            ->whereRaw("LOWER(REPLACE(heroes.name, ' ', '-')) <> heroes.slug")
            ->count();

        $catalogTotal = Hero::query()
            ->where('is_active', true)
            ->where('pending_review', false)
            ->count();

        $factionsCount = Faction::count();

        $formationsTotal = $user->formations()->count();

        $recentFormations = $user->formations()
            ->with('challengeType:id,code,name_pt')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get(['id', 'name', 'mode', 'teams_count', 'challenge_type_id', 'updated_at'])
            ->map(fn (Formation $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'mode' => $f->mode,
                'teams_count' => $f->teams_count,
                'challenge_label' => $f->challengeType?->name_pt ?? $f->challengeType?->code,
                'updated_at' => $f->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('dashboard', [
            'stats' => [
                'roster_total' => $rosterTotal,
                'roster_ascended' => $rosterAscended,
                'roster_awakened' => $rosterAwakened,
                'catalog_total' => $catalogTotal,
                'factions_count' => $factionsCount,
                'formations_total' => $formationsTotal,
            ],
            'rosterByFaction' => $rosterByFaction,
            'recentFormations' => $recentFormations,
        ]);
    }
}
