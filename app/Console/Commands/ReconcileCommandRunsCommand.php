<?php

namespace App\Console\Commands;

use App\Models\CommandRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileCommandRunsCommand extends Command
{
    protected $signature = 'command-runs:reconcile-stale';

    protected $description = 'Marca como failed CommandRuns em status=running cujo job não existe mais na fila (worker morreu sem completar)';

    public function handle(): int
    {
        $stale = CommandRun::query()
            ->where('status', CommandRun::STATUS_RUNNING)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('Nenhum CommandRun em status running.');

            return self::SUCCESS;
        }

        $jobIds = DB::table('jobs')->pluck('id')->all();
        $jobIdSet = array_flip($jobIds);

        $reconciled = 0;
        foreach ($stale as $run) {
            // Sem job_id registrado, ou job_id não está mais em jobs → órfão.
            if ($run->job_id !== null && isset($jobIdSet[$run->job_id])) {
                continue;
            }

            $run->update([
                'status' => CommandRun::STATUS_FAILED,
                'finished_at' => now(),
                'exit_code' => -1,
                'output' => trim(($run->output ?? '')."\n\n[Reconciliação] Worker do queue terminou sem finalizar o job. Status atualizado automaticamente."),
            ]);
            $reconciled++;
            $this->line("  ↳ CommandRun #{$run->id} ({$run->command}) marcado como failed");
        }

        if ($reconciled === 0) {
            $this->info("Todos os {$stale->count()} runs em running têm jobs ativos. Nada a reconciliar.");
        } else {
            $this->info("{$reconciled} CommandRun(s) órfão(s) marcado(s) como failed.");
        }

        return self::SUCCESS;
    }
}
