<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hero;
use App\Models\HeroStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeroStatsAdminController extends Controller
{
    private const FIELDS = ['hp', 'attack', 'defense', 'crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste'];

    public function update(Request $request, string $slug): JsonResponse
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
            return response()->json(['ok' => true, 'cleared' => true]);
        }

        $stats = HeroStat::updateOrCreate(['hero_id' => $hero->id], $data);

        return response()->json(['ok' => true, 'stats' => $stats]);
    }
}
