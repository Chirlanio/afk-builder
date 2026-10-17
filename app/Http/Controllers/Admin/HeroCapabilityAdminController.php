<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hero;
use App\Services\Optimization\HeroCapabilityClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Revisão do perfil de capacidades. O admin edita o perfil FINAL; o servidor
 * guarda só a diferença em relação ao classificador (capabilities_manual), então
 * reclassificar nunca desfaz uma correção.
 */
class HeroCapabilityAdminController extends Controller
{
    public function index(): Response
    {
        $heroes = Hero::query()
            ->where('is_active', true)
            ->with(['faction:id,code,name_pt', 'heroClass:id,code,name_pt'])
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'portrait_url', 'role_label', 'faction_id', 'hero_class_id', 'capabilities', 'capabilities_manual'])
            ->map(fn (Hero $hero) => [
                'id' => $hero->id,
                'slug' => $hero->slug,
                'name' => $hero->name,
                'portrait_url' => $hero->portrait_url,
                'role_label' => $hero->role_label,
                'faction' => $hero->faction?->only(['code', 'name_pt']),
                'hero_class' => $hero->heroClass?->only(['code', 'name_pt']),
                'classified' => $hero->capabilities ?? (object) [],
                'effective' => $hero->effectiveCapabilities() ?: (object) [],
                'has_manual' => ! empty($hero->capabilities_manual),
            ]);

        return Inertia::render('admin/heroes/capabilities', [
            'heroes' => $heroes,
            'tags' => HeroCapabilityClassifier::ALL,
        ]);
    }

    public function update(Request $request, Hero $hero): JsonResponse
    {
        $data = $request->validate([
            'capabilities' => ['present', 'array'],
            'capabilities.*' => ['integer', 'min:0', 'max:'.HeroCapabilityClassifier::MAX_STRENGTH],
        ]);

        $unknown = array_diff(array_keys($data['capabilities']), HeroCapabilityClassifier::ALL);
        if (! empty($unknown)) {
            throw ValidationException::withMessages(['capabilities' => 'Capacidade desconhecida: '.implode(', ', $unknown)]);
        }

        $desired = array_filter(array_map('intval', $data['capabilities']), fn ($strength) => $strength > 0);
        $classified = $hero->capabilities ?? [];

        $add = [];
        foreach ($desired as $tag => $strength) {
            if (($classified[$tag] ?? null) !== $strength) {
                $add[$tag] = $strength;
            }
        }
        $remove = array_values(array_diff(array_keys($classified), array_keys($desired)));

        $manual = array_filter(['add' => $add, 'remove' => $remove]);
        $hero->update(['capabilities_manual' => empty($manual) ? null : $manual]);

        return response()->json([
            'effective' => $hero->effectiveCapabilities() ?: (object) [],
            'has_manual' => ! empty($manual),
        ]);
    }

    public function reset(Hero $hero): JsonResponse
    {
        $hero->update(['capabilities_manual' => null]);

        return response()->json([
            'effective' => $hero->effectiveCapabilities() ?: (object) [],
            'has_manual' => false,
        ]);
    }
}
