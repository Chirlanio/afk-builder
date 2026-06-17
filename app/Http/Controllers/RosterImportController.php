<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Models\UserHero;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RosterImportController extends Controller
{
    public function index()
    {
        $heroes = Hero::select('id', 'name', 'thumbnail_url', 'faction_id')
            ->with('faction:id,name')
            ->get();

        return Inertia::render('roster/import', [
            'heroes' => $heroes,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'heroes' => 'required|array',
            'heroes.*.hero_id' => 'required|exists:heroes,id',
            'heroes.*.level' => 'nullable|integer|min:1',
            'heroes.*.ascension' => 'nullable|string',
        ]);

        foreach ($request->heroes as $heroData) {
            UserHero::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'hero_id' => $heroData['hero_id'],
                ],
                [
                    'level' => $heroData['level'] ?? 1,
                    // Note: We might want to map ascension_code here too
                ]
            );
        }

        return redirect()->route('heroes.index')->with('success', 'Roster importado com sucesso!');
    }
}
