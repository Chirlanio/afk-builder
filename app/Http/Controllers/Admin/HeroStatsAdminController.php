<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hero;
use App\Models\HeroStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeroStatsAdminController extends Controller
{
    private const CORE_FIELDS = ['hp', 'attack', 'defense', 'crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste'];

    public function update(Request $request, string $slug): JsonResponse
    {
        $hero = Hero::query()->where('slug', $slug)->firstOrFail();

        // hp/atk/def são inteiros grandes; demais (% ou números menores) decimais.
        $integerFields = ['hp', 'attack', 'defense'];
        $allFields = array_merge(self::CORE_FIELDS, HeroStat::EXTRA_FIELDS);

        $rules = [];
        foreach ($allFields as $f) {
            $type = in_array($f, $integerFields, true) ? 'integer' : 'numeric';
            $max = match ($f) {
                'hp' => 9_999_999_999,
                'attack', 'defense' => 999_999_999,
                default => 99_999_999.99,
            };
            $rules[$f] = ['nullable', $type, 'min:0', "max:{$max}"];
        }

        $validated = $request->validate($rules);

        // Normaliza: garante uma entrada para cada campo (null quando ausente)
        $data = [];
        foreach ($allFields as $f) {
            $data[$f] = $validated[$f] ?? null;
        }

        $allNull = ! collect($allFields)->some(fn ($f) => $data[$f] !== null);

        if ($allNull) {
            HeroStat::where('hero_id', $hero->id)->delete();

            return response()->json(['ok' => true, 'cleared' => true]);
        }

        $stats = HeroStat::updateOrCreate(['hero_id' => $hero->id], $data);

        return response()->json(['ok' => true, 'stats' => $stats]);
    }
}
