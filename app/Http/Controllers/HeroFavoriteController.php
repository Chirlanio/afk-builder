<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeroFavoriteController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    /**
     * Marca o herói como favorito da conta ativa. Idempotente — se já é favorito, no-op.
     */
    public function store(Request $request, Hero $hero): JsonResponse
    {
        $account = $this->active->get();

        $account->favoriteHeroes()->syncWithoutDetaching([$hero->id]);

        return response()->json([
            'is_favorited' => true,
            'favorites_count' => $account->favoriteHeroes()->count(),
        ]);
    }

    /**
     * Remove o herói dos favoritos. Idempotente — se não é favorito, no-op.
     */
    public function destroy(Request $request, Hero $hero): JsonResponse
    {
        $account = $this->active->get();

        $account->favoriteHeroes()->detach($hero->id);

        return response()->json([
            'is_favorited' => false,
            'favorites_count' => $account->favoriteHeroes()->count(),
        ]);
    }
}
