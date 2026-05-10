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

        // Caps relaxados para endgame ascended (Shemira HP ~664M, ATK/DEF chegam a ~30M).
        // Folga ampla para qualquer valor exibido em jogo.
        // hp/atk/def são inteiros; demais (crit/accuracy/dodge/haste) aceitam decimais
        // porque o jogo exibe valores como 25.5%, 30.7%.
        $integerFields = ['hp', 'attack', 'defense'];
        $rules = [];
        foreach (self::FIELDS as $f) {
            $type = in_array($f, $integerFields, true) ? 'integer' : 'numeric';
            $max = match ($f) {
                'hp' => 9_999_999_999,
                'attack', 'defense' => 999_999_999,
                default => 9_999,
            };
            $rules[$f] = ['nullable', $type, 'min:0', "max:{$max}"];
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
