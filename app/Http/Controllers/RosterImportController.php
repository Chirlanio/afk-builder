<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Services\ActiveGameAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RosterImportController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

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

        $account = $this->active->get();

        foreach ($request->heroes as $heroData) {
            $account->userHeroes()->updateOrCreate(
                ['hero_id' => $heroData['hero_id']],
                ['level' => $heroData['level'] ?? 1],
            );
        }

        return redirect()->route('heroes.index')->with('success', 'Roster importado com sucesso!');
    }
}
