<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\FormationVersion;
use App\Services\Formations\FormationVersioning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormationVersionController extends Controller
{
    public function index(Request $request, Formation $formation): JsonResponse
    {
        abort_unless($formation->user_id === $request->user()->id, 403);

        $versions = $formation->versions()
            ->orderByDesc('created_at')
            ->get(['id', 'created_at', 'snapshot']);

        return response()->json([
            'versions' => $versions->map(fn (FormationVersion $v) => [
                'id' => $v->id,
                'created_at' => $v->created_at?->toIso8601String(),
                'team_count' => count($v->snapshot['teams'] ?? []),
                'hero_count' => collect($v->snapshot['teams'] ?? [])
                    ->sum(fn ($t) => count($t['heroes'] ?? [])),
                'name' => $v->snapshot['name'] ?? null,
            ])->values(),
        ]);
    }

    public function restore(
        Request $request,
        Formation $formation,
        FormationVersion $version,
        FormationVersioning $versioning,
    ): RedirectResponse {
        abort_unless($formation->user_id === $request->user()->id, 403);

        if ($version->formation_id !== $formation->id) {
            abort(404);
        }

        $versioning->snapshot($formation);
        $versioning->restore($formation, $version);

        return back()->with('flash', 'Versão restaurada.');
    }
}
