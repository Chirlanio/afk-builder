<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Models\UserHero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserHeroController extends Controller
{
    private const ASCENSION_CODES = [
        'common', 'common_plus', 'rare', 'rare_plus',
        'elite', 'elite_plus', 'legendary', 'legendary_plus',
        'mythic', 'mythic_plus',
        'ascended', 'ascended_plus',
        'ascended_1_star', 'ascended_2_stars', 'ascended_3_stars',
        'ascended_4_stars', 'ascended_5_stars',
    ];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hero_id' => ['required', 'integer', Rule::exists('heroes', 'id')->where('is_active', true)],
            'level' => ['required', 'integer', 'min:1', 'max:240'],
            'ascension_code' => ['nullable', 'string', Rule::in(self::ASCENSION_CODES)],
            'signature_item' => ['nullable', 'integer', 'min:0', 'max:40'],
            'furniture' => ['nullable', 'integer', 'min:0', 'max:9'],
            'engraving' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $hero = Hero::with('tier')->findOrFail($data['hero_id']);

        if ($data['level'] > $hero->tier->max_level) {
            $data['level'] = $hero->tier->max_level;
        }

        $request->user()->userHeroes()->updateOrCreate(
            ['hero_id' => $data['hero_id']],
            [
                'level' => $data['level'],
                'ascension_code' => $data['ascension_code'] ?? null,
                'ascension_value' => $this->ascensionValue($data['ascension_code'] ?? null),
                'signature_item' => $data['signature_item'] ?? 0,
                'furniture' => $data['furniture'] ?? 0,
                'engraving' => $data['engraving'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back();
    }

    public function update(Request $request, UserHero $userHero): RedirectResponse
    {
        abort_unless($userHero->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'level' => ['required', 'integer', 'min:1', 'max:240'],
            'ascension_code' => ['nullable', 'string', Rule::in(self::ASCENSION_CODES)],
            'signature_item' => ['nullable', 'integer', 'min:0', 'max:40'],
            'furniture' => ['nullable', 'integer', 'min:0', 'max:9'],
            'engraving' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $hero = $userHero->hero()->with('tier')->first();

        if ($hero && $data['level'] > $hero->tier->max_level) {
            $data['level'] = $hero->tier->max_level;
        }

        $userHero->update([
            'level' => $data['level'],
            'ascension_code' => $data['ascension_code'] ?? null,
            'ascension_value' => $this->ascensionValue($data['ascension_code'] ?? null),
            'signature_item' => $data['signature_item'] ?? 0,
            'furniture' => $data['furniture'] ?? 0,
            'engraving' => $data['engraving'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return back();
    }

    public function destroy(Request $request, UserHero $userHero): RedirectResponse
    {
        abort_unless($userHero->user_id === $request->user()->id, 403);

        $userHero->delete();

        return back();
    }

    private function ascensionValue(?string $code): int
    {
        if ($code === null) {
            return 0;
        }

        $index = array_search($code, self::ASCENSION_CODES, true);

        return $index === false ? 0 : $index;
    }
}
