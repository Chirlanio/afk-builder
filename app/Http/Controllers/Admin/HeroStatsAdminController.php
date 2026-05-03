<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hero;
use App\Models\HeroStat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HeroStatsAdminController extends Controller
{
    private const FIELDS = ['hp', 'attack', 'defense', 'crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste'];

    public function edit(string $slug): Response
    {
        $hero = Hero::query()
            ->where('slug', $slug)
            ->with(['faction:id,code,name_pt', 'heroClass:id,code,name_pt', 'tier:id,code,name_pt', 'stats'])
            ->firstOrFail();

        return Inertia::render('admin/heroes/stats', [
            'hero' => $hero,
            'stats' => $hero->stats,
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $hero = Hero::query()->where('slug', $slug)->firstOrFail();

        $rules = [];
        foreach (self::FIELDS as $f) {
            // crit/accuracy/dodge/haste tipicamente ≤ 1000 (basis points), HP/ATK/DEF podem ser grandes
            $max = in_array($f, ['hp', 'attack', 'defense'], true) ? 999_999 : 9_999;
            $rules[$f] = ['nullable', 'integer', 'min:0', "max:{$max}"];
        }

        $validated = $request->validate($rules);

        // Normaliza: garante uma entrada para cada campo (null quando ausente)
        $data = [];
        foreach (self::FIELDS as $f) {
            $data[$f] = $validated[$f] ?? null;
        }

        $allNull = ! collect(self::FIELDS)->some(fn ($f) => $data[$f] !== null);

        if ($allNull) {
            HeroStat::where('hero_id', $hero->id)->delete();
        } else {
            HeroStat::updateOrCreate(['hero_id' => $hero->id], $data);
        }

        return redirect()
            ->route('heroes.show', ['slug' => $hero->slug])
            ->with('flash', "Stats de '{$hero->name}' atualizadas.");
    }
}
