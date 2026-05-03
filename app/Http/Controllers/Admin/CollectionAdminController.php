<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CollectionAdminController extends Controller
{
    public function index(): Response
    {
        $collections = Collection::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/collections/index', [
            'collections' => $collections,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $collection = Collection::create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['ok' => true, 'collection' => $collection]);
    }

    public function update(Request $request, Collection $collection): JsonResponse
    {
        $data = $this->validatePayload($request, $collection->id);

        $collection->update($data);

        return response()->json(['ok' => true, 'collection' => $collection->fresh()]);
    }

    public function destroy(Collection $collection): RedirectResponse
    {
        $collection->delete();

        return back()->with('flash', "Coleção '{$collection->name}' removida.");
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = ['required', 'string', 'max:128', 'regex:/^[a-z0-9-]+$/'];
        $slugRule[] = $ignoreId
            ? Rule::unique('collections', 'slug')->ignore($ignoreId)
            : Rule::unique('collections', 'slug');

        return $request->validate([
            'slug' => $slugRule,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hp_bonus' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'attack_bonus' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'defense_bonus' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'portrait_url' => ['nullable', 'url', 'max:500'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
