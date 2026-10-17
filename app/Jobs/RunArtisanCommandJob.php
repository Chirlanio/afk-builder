<?php

namespace App\Jobs;

use App\Models\CommandRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class RunArtisanCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $commandRunId) {}

    public function handle(): void
    {
        $run = CommandRun::find($this->commandRunId);
        if (! $run) {
            Log::warning('RunArtisanCommandJob: CommandRun não encontrado', ['id' => $this->commandRunId]);

            return;
        }

        // Race condition: o usuário pode ter pedido cancelamento entre dispatch e o
        // worker pegar o job. Como o controller deleta a row em `jobs` ao cancelar,
        // chegar aqui significa que o job vazou — apenas respeita o cancelamento.
        if ($run->status === CommandRun::STATUS_CANCELLED) {
            Log::info('RunArtisanCommandJob: pulando job já cancelado', ['id' => $this->commandRunId]);

            return;
        }

        $run->update([
            'status' => CommandRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $output = new BufferedOutput;

        try {
            $exitCode = Artisan::call($run->command, $run->args ?? [], $output);

            $run->update([
                'status' => $exitCode === 0 ? CommandRun::STATUS_COMPLETED : CommandRun::STATUS_FAILED,
                'finished_at' => now(),
                'exit_code' => $exitCode,
                'output' => $this->truncate($output->fetch()),
            ]);
        } catch (Throwable $e) {
            $stderr = $output->fetch().PHP_EOL.'[Exception] '.$e::class.': '.$e->getMessage();

            $run->update([
                'status' => CommandRun::STATUS_FAILED,
                'finished_at' => now(),
                'exit_code' => 1,
                'output' => $this->truncate($stderr),
            ]);

            Log::error('RunArtisanCommandJob falhou', [
                'command_run_id' => $run->id,
                'command' => $run->command,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    private function truncate(string $text): string
    {
        if (strlen($text) <= CommandRun::OUTPUT_MAX_BYTES) {
            return $text;
        }

        $truncated = substr($text, 0, CommandRun::OUTPUT_MAX_BYTES - 200);

        return $truncated."\n\n[...output truncado em ".CommandRun::OUTPUT_MAX_BYTES.' bytes]';
    }
}
