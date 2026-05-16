<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FormationShareController extends Controller
{
    public function share(Request $request, Formation $formation): JsonResponse
    {
        abort_unless($formation->user_id === $request->user()->id, 403);

        if ($formation->share_token === null) {
            $formation->update(['share_token' => Str::random(32)]);
        }

        return response()->json([
            'share_token' => $formation->share_token,
            'public_url' => url('/f/'.$formation->share_token),
        ]);
    }

    public function revoke(Request $request, Formation $formation): JsonResponse
    {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $formation->update(['share_token' => null]);

        return response()->json(['ok' => true]);
    }

    public function publicShow(string $token): Response
    {
        $formation = Formation::where('share_token', $token)
            ->with([
                'challengeType:id,code,name_pt,description,is_pvp,teams_must_be_unique',
                'teams.teamHeroes:id,formation_team_id,hero_id,slot,row',
                'teams.beast:id,slug,name,portrait_url,strength_buff,intelligence_buff,agility_buff',
                'user:id,name',
            ])
            ->firstOrFail();

        $heroIds = $formation->teams
            ->flatMap(fn ($team) => $team->teamHeroes->pluck('hero_id'))
            ->unique()
            ->values();

        $heroes = Hero::query()
            ->whereIn('id', $heroIds)
            ->with(['faction:id,code', 'heroClass:id,code', 'tier:id,code'])
            ->get([
                'id', 'slug', 'name', 'title',
                'faction_id', 'hero_class_id', 'tier_id',
                'gender', 'role_label', 'portrait_url',
            ])
            ->map(fn (Hero $h) => array_merge($h->toArray(), [
                'is_awakened' => Str::slug($h->name) !== $h->slug,
            ]))
            ->all();

        return Inertia::render('formations/public', [
            'formation' => $formation,
            'heroes' => $heroes,
            'factions' => Faction::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']),
            'heroClasses' => HeroClass::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'display_order']),
            'tiers' => Tier::orderBy('display_order')
                ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']),
        ]);
    }
}
