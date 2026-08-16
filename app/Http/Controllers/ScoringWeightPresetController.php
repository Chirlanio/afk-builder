<?php

namespace App\Http\Controllers;

use App\Models\ScoringWeightPreset;
use App\Services\ActiveGameAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScoringWeightPresetController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(Request $request): JsonResponse
    {
        $presets = $this->active->get()
            ->scoringWeightPresets()
            ->orderBy('name')
            ->get(['id', 'name', 'challenge_type_id', 'weights']);

        return response()->json(['presets' => $presets]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $account = $this->active->get();

        $exists = $account
            ->scoringWeightPresets()
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Já existe um preset com este nome.',
                'errors' => ['name' => ['Já existe um preset com este nome.']],
            ], 422);
        }

        $preset = $account->scoringWeightPresets()->create($data);

        return response()->json([
            'preset' => $preset->only(['id', 'name', 'challenge_type_id', 'weights']),
        ], 201);
    }

    public function update(Request $request, ScoringWeightPreset $preset): JsonResponse
    {
        abort_unless($preset->game_account_id === $this->active->get()->id, 403);

        $data = $this->validatedPayload($request, $preset->id);

        $preset->update($data);

        return response()->json([
            'preset' => $preset->fresh()->only(['id', 'name', 'challenge_type_id', 'weights']),
        ]);
    }

    public function destroy(Request $request, ScoringWeightPreset $preset): JsonResponse
    {
        abort_unless($preset->game_account_id === $this->active->get()->id, 403);

        $preset->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{name: string, challenge_type_id: int|null, weights: array<string, float>}
     */
    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        $allowedKeys = ScoringWeightPreset::WEIGHT_KEYS;

        // Valida cada peso conhecido individualmente (em vez de wildcard `weights.*`)
        // para que chaves desconhecidas sejam ignoradas silenciosamente pelo validate
        // — assim postar `{weights: {power_weight: 2, malicious_key: 99}}` não rejeita.
        $rules = [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:60',
                Rule::unique('scoring_weight_presets', 'name')
                    ->where('game_account_id', $this->active->get()->id)
                    ->ignore($ignoreId),
            ],
            'challenge_type_id' => ['nullable', 'integer', 'exists:challenge_types,id'],
            'weights' => ['required', 'array'],
        ];
        foreach ($allowedKeys as $key) {
            $rules["weights.{$key}"] = ['nullable', 'numeric', 'min:0', 'max:3'];
        }

        $data = $request->validate($rules);

        // `weights` no `$data` só contém as chaves conhecidas validadas (Laravel descarta o resto).
        $weights = $data['weights'];

        // Normaliza pra float (Eloquent serializa double consistente).
        foreach ($weights as $key => $value) {
            $weights[$key] = (float) $value;
        }

        return [
            'name' => $data['name'],
            'challenge_type_id' => $data['challenge_type_id'] ?? null,
            'weights' => $weights,
        ];
    }
}
