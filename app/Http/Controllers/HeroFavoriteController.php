<?php

namespace App\Http\Controllers;

use App\Models\Hero;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeroFavoriteController extends Controller
{
    /**
     * Marca o herói como favorito do user. Idempotente — se já é favorito, no-op.
     */
    public function store(Request $request, Hero $hero): JsonResponse
    {
        $user = $request->user();

        $user->favoriteHeroes()->syncWithoutDetaching([$hero->id]);

        return response()->json([
            'is_favorited' => true,
            'favorites_count' => $user->favoriteHeroes()->count(),
        ]);
    }

    /**
     * Remove o herói dos favoritos. Idempotente — se não é favorito, no-op.
     */
    public function destroy(Request $request, Hero $hero): JsonResponse
    {
        $user = $request->user();

        $user->favoriteHeroes()->detach($hero->id);

        return response()->json([
            'is_favorited' => false,
            'favorites_count' => $user->favoriteHeroes()->count(),
        ]);
    }
}
