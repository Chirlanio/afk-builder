<?php

namespace App\Http\Controllers;

use App\Models\FormationTag;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FormationTagController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(Request $request): JsonResponse
    {
        $tags = $this->active->get()
            ->formationTags()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json(['tags' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $tag = $this->active->get()->formationTags()->create($data);

        return response()->json(['tag' => $tag->only(['id', 'name', 'color'])], 201);
    }

    public function update(Request $request, FormationTag $tag): JsonResponse
    {
        abort_unless($tag->game_account_id === $this->active->get()->id, 403);

        $data = $this->validatedPayload($request, $tag->id);

        $tag->update($data);

        return response()->json(['tag' => $tag->fresh()->only(['id', 'name', 'color'])]);
    }

    public function destroy(Request $request, FormationTag $tag): JsonResponse
    {
        abort_unless($tag->game_account_id === $this->active->get()->id, 403);

        $tag->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{name: string, color: string}
     */
    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:30',
                Rule::unique('formation_tags', 'name')
                    ->where('game_account_id', $this->active->get()->id)
                    ->ignore($ignoreId),
            ],
            'color' => ['nullable', 'string', Rule::in(FormationTag::COLORS)],
        ]);

        return [
            'name' => trim($data['name']),
            'color' => $data['color'] ?? FormationTag::DEFAULT_COLOR,
        ];
    }
}
