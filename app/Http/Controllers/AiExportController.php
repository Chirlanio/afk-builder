<?php

namespace App\Http\Controllers;

use App\Enums\Planning;
use App\Services\ActiveGameAccount;
use App\Services\AiExport\AiExportObjective;
use App\Services\AiExport\AiExportObjectives;
use App\Services\AiExport\AiExportService;
use App\Services\Counters\EnemyTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Export para IA: devolve o texto (prompt + dados) da Conta de jogo ativa
 * para o Usuário colar no modelo de IA que quiser.
 */
class AiExportController extends Controller
{
    public function __construct(
        private readonly ActiveGameAccount $active,
        private readonly AiExportService $exporter,
    ) {}

    /**
     * O catálogo de Objetivos, na ordem do modal, com os campos de cada um.
     */
    public function objectives(): JsonResponse
    {
        return response()->json(array_values(array_map(
            fn (AiExportObjective $objective) => $objective->toArray(),
            AiExportObjectives::all(),
        )));
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'objective' => ['required', 'string', Rule::in(AiExportObjectives::codes())],
            'planning' => ['required', Rule::enum(Planning::class)],
            'include_suggestion' => ['nullable', 'boolean'],
            'detail' => ['nullable', Rule::in(['full', 'compact'])],
        ]);
        $objective = AiExportObjectives::get($data['objective']);
        $params = $request->validate($objective->rules());

        return response()->json(
            $this->exporter->build(
                $this->active->get(),
                $objective,
                Planning::from($data['planning']),
                $params,
                $request->boolean('include_suggestion'),
                ($data['detail'] ?? 'full') === 'full',
                $request->has('enemy') ? EnemyTeam::fromRequest($request)->all() : [],
            ),
        );
    }
}
