<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Beast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BeastAdminController extends Controller
{
    public function index(): Response
    {
        $beasts = Beast::query()->orderBy('name')->get();

        return Inertia::render('admin/beasts/index', [
            'beasts' => $beasts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $beast = Beast::create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['ok' => true, 'beast' => $beast]);
    }

    public function update(Request $request, Beast $beast): JsonResponse
    {
        $data = $this->validatePayload($request, $beast->id);

        $beast->update($data);

        return response()->json(['ok' => true, 'beast' => $beast->fresh()]);
    }

    public function destroy(Beast $beast): RedirectResponse
    {
        $beast->delete();

        return back()->with('flash', "Beast '{$beast->name}' removido.");
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:2048'],
        ]);

        $path = $request->file('image')->store('beasts', 'public');

        return response()->json([
            'ok' => true,
            'url' => Storage::url($path),
        ]);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = ['required', 'string', 'max:128', 'regex:/^[a-z0-9-]+$/'];
        $slugRule[] = $ignoreId
            ? Rule::unique('beasts', 'slug')->ignore($ignoreId)
            : Rule::unique('beasts', 'slug');

        return $request->validate([
            'slug' => $slugRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'strength_buff' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'intelligence_buff' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'agility_buff' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'tier' => ['nullable', Rule::in(['S+', 'S', 'A', 'B', 'C', 'D', 'F'])],
            'tier_note' => ['nullable', 'string', 'max:500'],
            'portrait_url' => ['nullable', 'string', 'max:500', function (string $attr, mixed $value, \Closure $fail) {
                if (str_starts_with((string) $value, '/storage/')) {
                    return;
                }
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return;
                }
                $fail('A URL da imagem deve ser absoluta (https://...) ou um caminho local (/storage/...).');
            }],
            'source_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
