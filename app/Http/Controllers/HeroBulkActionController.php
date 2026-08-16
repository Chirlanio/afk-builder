<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeroBulkActionController extends Controller
{
    public const MAX_PER_REQUEST = 200;

    public function __construct(private readonly ActiveGameAccount $active) {}

    /**
     * Adiciona/remove vários heroes dos favoritos em uma chamada.
     * Idempotente: add usa syncWithoutDetaching, remove usa detach.
     */
    public function favorites(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $account = $this->active->get();

        // Filtra IDs realmente existentes pra evitar attach com FK inválida
        $validIds = Hero::whereIn('id', $data['hero_ids'])->pluck('id')->all();

        if ($data['action'] === 'add') {
            $account->favoriteHeroes()->syncWithoutDetaching($validIds);
        } else {
            $account->favoriteHeroes()->detach($validIds);
        }

        return response()->json([
            'action' => $data['action'],
            'affected' => count($validIds),
            'favorites_count' => $account->favoriteHeroes()->count(),
        ]);
    }

    /**
     * Adiciona/remove vários heroes do roster (UserHero) em lote.
     * - add: cria UserHero com defaults pra IDs que ainda não estão no roster
     * - remove: deleta UserHero pra IDs que estão no roster
     */
    public function roster(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $account = $this->active->get();
        $heroIds = $data['hero_ids'];

        if ($data['action'] === 'add') {
            $existingHeroIds = $account->userHeroes()
                ->whereIn('hero_id', $heroIds)
                ->pluck('hero_id')
                ->all();
            $missing = array_diff($heroIds, $existingHeroIds);
            $validNew = Hero::whereIn('id', $missing)->pluck('id')->all();

            foreach ($validNew as $heroId) {
                $account->userHeroes()->create([
                    'hero_id' => $heroId,
                    'level' => 1,
                    'ascension_code' => null,
                    'ascension_value' => 0,
                    'signature_item' => 0,
                    'furniture' => 0,
                    'engraving' => 0,
                ]);
            }

            return response()->json([
                'action' => 'add',
                'affected' => count($validNew),
                'roster_count' => $account->userHeroes()->count(),
            ]);
        }

        $deleted = $account->userHeroes()
            ->whereIn('hero_id', $heroIds)
            ->delete();

        return response()->json([
            'action' => 'remove',
            'affected' => $deleted,
            'roster_count' => $account->userHeroes()->count(),
        ]);
    }

    /**
     * @return array{hero_ids: array<int, int>, action: 'add'|'remove'}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'hero_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PER_REQUEST],
            'hero_ids.*' => ['integer', 'min:1'],
            'action' => ['required', 'string', Rule::in(['add', 'remove'])],
        ]);

        return [
            'hero_ids' => array_values(array_unique($data['hero_ids'])),
            'action' => $data['action'],
        ];
    }
}
