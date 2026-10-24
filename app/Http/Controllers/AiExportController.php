<?php

namespace App\Http\Controllers;

use App\Services\ActiveGameAccount;
use App\Services\AiExport\AiExportObjectives;
use App\Services\AiExport\AiExportService;
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

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'objective' => ['required', 'string', Rule::in(AiExportObjectives::codes())],
            'planning' => ['required', 'string', Rule::in(['real', 'target'])],
            'include_suggestion' => ['nullable', 'boolean'],
        ]);
        $params = $request->validate(AiExportObjectives::get($data['objective'])['rules']);

        return response()->json(
            $this->exporter->build(
                $this->active->get(),
                $data['objective'],
                $data['planning'],
                $params,
                (bool) ($data['include_suggestion'] ?? false),
            ),
        );
    }
}
