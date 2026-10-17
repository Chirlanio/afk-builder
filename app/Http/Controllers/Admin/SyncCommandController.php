<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunArtisanCommandJob;
use App\Models\CommandRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SyncCommandController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/sync/index', [
            'commands' => $this->whitelist(),
            'runs' => $this->loadRecentRuns(),
            'queueDriver' => config('queue.default'),
        ]);
    }

    public function runs(): JsonResponse
    {
        return response()->json([
            'runs' => $this->loadRecentRuns(),
        ]);
    }

    public function show(CommandRun $run): JsonResponse
    {
        return response()->json([
            'run' => [
                'id' => $run->id,
                'command' => $run->command,
                'args' => $run->args ?? [],
                'status' => $run->status,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'duration_seconds' => $run->durationSeconds(),
                'exit_code' => $run->exit_code,
                'output' => $run->output ?? '',
                'user_name' => $run->user?->name,
                'created_at' => $run->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $whitelist = config('sync_commands');

        $validated = $request->validate([
            'command' => ['required', 'string', 'in:'.implode(',', array_keys($whitelist))],
            'args' => ['nullable', 'array'],
        ]);

        $command = $validated['command'];
        $rawArgs = $validated['args'] ?? [];
        $config = $whitelist[$command];

        $args = $this->validateArgs($rawArgs, $config['options'] ?? []);

        $run = DB::transaction(function () use ($request, $command, $args) {
            return CommandRun::create([
                'command' => $command,
                'args' => $args,
                'status' => CommandRun::STATUS_PENDING,
                'user_id' => $request->user()?->id,
            ]);
        });

        RunArtisanCommandJob::dispatch($run->id);

        // Captura o id do job recém-enfileirado pra suportar cancelamento.
        // Não é atômico (race com outro dispatch concorrente), mas como /admin/sync
        // é admin-only e dispatch é raro, é tolerável. Best-effort.
        $jobId = DB::table('jobs')
            ->where('queue', config('queue.connections.database.queue', 'default'))
            ->latest('id')
            ->value('id');

        if ($jobId) {
            $run->update(['job_id' => $jobId]);
        }

        return response()->json([
            'run' => [
                'id' => $run->id,
                'command' => $run->command,
                'args' => $run->args,
                'status' => $run->status,
                'created_at' => $run->created_at?->toIso8601String(),
            ],
        ], 202);
    }

    public function cancel(CommandRun $run): JsonResponse
    {
        if ($run->status !== CommandRun::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => "Apenas execuções pendentes podem ser canceladas. Esta está em '{$run->status}'.",
            ]);
        }

        DB::transaction(function () use ($run) {
            // Lock pra evitar race com o worker pegando o job entre o select e o update.
            $locked = CommandRun::lockForUpdate()->find($run->id);

            if ($locked->status !== CommandRun::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => "Execução saiu do estado pendente durante o cancelamento (agora está em '{$locked->status}').",
                ]);
            }

            $locked->update([
                'status' => CommandRun::STATUS_CANCELLED,
                'finished_at' => now(),
            ]);

            // Remove o job da fila se ainda estiver lá. Best-effort: se o worker já
            // pegou, a row foi deletada de jobs, e o handle() vai pular ao ver status
            // cancelled (safeguard em RunArtisanCommandJob::handle).
            if ($locked->job_id) {
                DB::table('jobs')->where('id', $locked->job_id)->delete();
            }
        });

        return response()->json([
            'run' => [
                'id' => $run->id,
                'status' => CommandRun::STATUS_CANCELLED,
            ],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function whitelist(): array
    {
        $config = config('sync_commands');

        $out = [];
        foreach ($config as $signature => $entry) {
            $out[$signature] = [
                'signature' => $signature,
                'label' => $entry['label'],
                'description' => $entry['description'] ?? null,
                'icon' => $entry['icon'] ?? 'Terminal',
                'options' => array_map(function ($key, $opt) {
                    return [
                        'name' => $key,
                        'type' => $opt['type'],
                        'label' => $opt['label'],
                        'placeholder' => $opt['placeholder'] ?? null,
                        'options' => $opt['options'] ?? null,
                        'default' => $opt['default'] ?? null,
                        'min' => $opt['min'] ?? null,
                        'max' => $opt['max'] ?? null,
                    ];
                }, array_keys($entry['options'] ?? []), $entry['options'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRecentRuns(): array
    {
        return CommandRun::query()
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (CommandRun $r) => [
                'id' => $r->id,
                'command' => $r->command,
                'args' => $r->args ?? [],
                'status' => $r->status,
                'started_at' => $r->started_at?->toIso8601String(),
                'finished_at' => $r->finished_at?->toIso8601String(),
                'duration_seconds' => $r->durationSeconds(),
                'exit_code' => $r->exit_code,
                'user_name' => $r->user?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Valida e converte os args raw enviados pelo frontend para o formato do
     * Artisan::call (--key => value). Apenas options declaradas na whitelist
     * são aceitas; o resto é descartado.
     *
     * @param  array<string, mixed>  $rawArgs
     * @param  array<string, array<string, mixed>>  $optionsConfig
     * @return array<string, mixed>
     */
    private function validateArgs(array $rawArgs, array $optionsConfig): array
    {
        $out = [];

        foreach ($optionsConfig as $name => $opt) {
            if (! array_key_exists($name, $rawArgs)) {
                continue;
            }
            $value = $rawArgs[$name];
            if ($value === null || $value === '' || $value === false) {
                continue;
            }

            $out['--'.$name] = match ($opt['type']) {
                'bool' => true,
                'int' => $this->coerceInt($value, $name, $opt),
                'string' => $this->coerceString($value, $name, $opt),
                'enum' => $this->coerceEnum($value, $name, $opt),
                default => throw ValidationException::withMessages([$name => "Tipo desconhecido: {$opt['type']}"]),
            };

            // Para flags booleanas, Artisan::call espera só a chave (sem valor).
            // Mas para options com valor (--limit=N), espera 'value' diretamente.
            if ($opt['type'] === 'bool') {
                // mantém true (Artisan aceita)
                continue;
            }

            // Garante que o valor já está atribuído acima.
        }

        return $out;
    }

    private function coerceInt(mixed $value, string $name, array $opt): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([$name => 'Deve ser um número inteiro']);
        }
        $int = (int) $value;
        if (isset($opt['min']) && $int < $opt['min']) {
            throw ValidationException::withMessages([$name => "Mínimo: {$opt['min']}"]);
        }
        if (isset($opt['max']) && $int > $opt['max']) {
            throw ValidationException::withMessages([$name => "Máximo: {$opt['max']}"]);
        }

        return $int;
    }

    private function coerceString(mixed $value, string $name, array $opt): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$name => 'Deve ser uma string']);
        }
        $value = trim($value);
        if (isset($opt['pattern']) && ! preg_match($opt['pattern'], $value)) {
            throw ValidationException::withMessages([$name => 'Formato inválido']);
        }

        return $value;
    }

    private function coerceEnum(mixed $value, string $name, array $opt): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$name => 'Valor inválido']);
        }
        $allowed = array_keys($opt['options'] ?? []);
        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([$name => 'Valor não permitido']);
        }

        return $value;
    }
}
