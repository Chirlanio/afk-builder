<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroFeature;
use App\Models\UserHero;
use App\Services\Optimization\PowerCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, PowerCalculator $power): Response
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

        $rosterByClass = $user->userHeroes()
            ->join('heroes', 'user_heroes.hero_id', '=', 'heroes.id')
            ->join('hero_classes', 'heroes.hero_class_id', '=', 'hero_classes.id')
            ->groupBy('hero_classes.code', 'hero_classes.name_pt', 'hero_classes.display_order')
            ->orderBy('hero_classes.display_order')
            ->select('hero_classes.code', 'hero_classes.name_pt', DB::raw('COUNT(*) as total'))
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name_pt' => $row->name_pt,
                'total' => (int) $row->total,
            ]);

        $rosterByTier = $user->userHeroes()
            ->join('heroes', 'user_heroes.hero_id', '=', 'heroes.id')
            ->join('tiers', 'heroes.tier_id', '=', 'tiers.id')
            ->groupBy('tiers.code', 'tiers.name_pt', 'tiers.display_order')
            ->orderBy('tiers.display_order')
            ->select('tiers.code', 'tiers.name_pt', DB::raw('COUNT(*) as total'))
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

        // Fase 28: milestones — heroes com investimento maximo
        $rosterSi30 = $user->userHeroes()->where('signature_item', '>=', 30)->count();
        $rosterSi40 = $user->userHeroes()->where('signature_item', '>=', 40)->count();
        $rosterF9 = $user->userHeroes()->where('furniture', '>=', 9)->count();
        $rosterEng80 = $user->userHeroes()->where('engraving', '>=', 80)->count();

        // Top 5 por power_rating computado via PowerCalculator
        $topPowerHeroes = $user->userHeroes()
            ->with([
                'hero:id,slug,name,portrait_url,faction_id,hero_class_id,tier_id',
                'hero.faction:id,code',
                'hero.heroClass:id,code',
                'hero.tier:id,code',
                'hero.stats',
                'artifact:id,attributes',
                'collection:id,hp_bonus,attack_bonus,defense_bonus',
            ])
            ->get()
            ->map(fn (UserHero $uh) => [
                'user_hero' => $uh,
                'power' => $power->forUserHero($uh, $uh->hero),
            ])
            ->sortByDesc('power')
            ->take(5)
            ->values()
            ->map(fn ($row) => [
                'hero_slug' => $row['user_hero']->hero?->slug,
                'hero_name' => $row['user_hero']->hero?->name,
                'portrait_url' => $row['user_hero']->hero?->portrait_url,
                'faction_code' => $row['user_hero']->hero?->faction?->code,
                'class_code' => $row['user_hero']->hero?->heroClass?->code,
                'tier_code' => $row['user_hero']->hero?->tier?->code,
                'power' => (int) round($row['power']),
                'signature_item' => $row['user_hero']->signature_item,
                'furniture' => $row['user_hero']->furniture,
                'engraving' => $row['user_hero']->engraving,
                'level' => $row['user_hero']->level,
            ]);

        // Heroes do roster que têm features destacadas (PvP Top, Twisted Realm Top)
        $rosterHeroIds = $user->userHeroes()->pluck('hero_id')->all();
        $featuredInRoster = empty($rosterHeroIds) ? collect() : HeroFeature::query()
            ->whereIn('hero_id', $rosterHeroIds)
            ->whereIn('tag', [HeroFeature::TAG_PVP_TOP, HeroFeature::TAG_TWISTED_REALM_TOP])
            ->with(['hero:id,slug,name,portrait_url'])
            ->get()
            ->groupBy('tag')
            ->map(fn ($features) => $features->map(fn ($f) => [
                'slug' => $f->hero?->slug,
                'name' => $f->hero?->name,
                'portrait_url' => $f->hero?->portrait_url,
            ])->values());

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
                'roster_si_30' => $rosterSi30,
                'roster_si_40' => $rosterSi40,
                'roster_f9' => $rosterF9,
                'roster_eng_80' => $rosterEng80,
                'catalog_total' => $catalogTotal,
                'factions_count' => $factionsCount,
                'formations_total' => $formationsTotal,
            ],
            'rosterByFaction' => $rosterByFaction,
            'rosterByClass' => $rosterByClass,
            'rosterByTier' => $rosterByTier,
            'topPowerHeroes' => $topPowerHeroes,
            'featuredInRoster' => [
                'pvp_top' => $featuredInRoster->get(HeroFeature::TAG_PVP_TOP, collect())->all(),
                'twisted_realm_top' => $featuredInRoster->get(HeroFeature::TAG_TWISTED_REALM_TOP, collect())->all(),
            ],
            'recentFormations' => $recentFormations,
        ]);
    }
}
