<?php

namespace App\Console\Commands;

use App\Services\HeroCatalogSync\FandomSyncService;
use App\Services\HeroCatalogSync\SyncOptions;
use App\Services\HeroCatalogSync\SyncResult;
use Illuminate\Console\Command;

class SyncHeroesCommand extends Command
{
    protected $signature = 'heroes:sync
        {--initial : Trata como primeiro import: novos heróis entram já ativos (sem pending_review)}
        {--dry-run : Não escreve no banco, apenas relata o que faria}
        {--limit= : Limita o nº de páginas processadas (útil para teste)}
        {--page= : Sincroniza apenas uma página específica pelo título (ex: --page=Shemira)}';

    protected $description = 'Sincroniza o catálogo de heróis com a wiki do AFK Arena (Fandom MediaWiki API).';

    public function handle(FandomSyncService $service): int
    {
        $options = new SyncOptions(
            initial: (bool) $this->option('initial'),
            dryRun: (bool) $this->option('dry-run'),
            limit: $this->option('limit') !== null ? (int) $this->option('limit') : null,
            onProgress: function (int $current, int $total, string $title, string $action) {
                $marker = match ($action) {
                    'created' => '<fg=green>+</>',
                    'updated' => '<fg=cyan>~</>',
                    'skipped' => '<fg=yellow>·</>',
                    'error' => '<fg=red>!</>',
                    default => ' ',
                };
                $this->line(sprintf('  %s [%d/%d] %s (%s)', $marker, $current, $total, $title, $action));
            },
        );

        $page = $this->option('page');

        if ($options->dryRun) {
            $this->warn('[dry-run] Nenhuma alteração será persistida.');
        }

        if ($options->initial) {
            $this->info('[initial] Novos heróis entrarão ativos (sem pending_review).');
        }

        $this->info($page ? "Sincronizando página: {$page}" : 'Listando heróis na wiki...');

        $start = microtime(true);

        $result = $page
            ? $service->syncSingleTitle($page, $options)
            : $service->syncAll($options);

        $elapsed = round(microtime(true) - $start, 2);

        $this->renderSummary($result, $elapsed);

        return self::SUCCESS;
    }

    private function renderSummary(SyncResult $result, float $elapsed): void
    {
        $this->newLine();
        $this->info("Concluído em {$elapsed}s.");

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Páginas vistas', $result->seen],
                ['Heróis criados', $result->created],
                ['Heróis atualizados', $result->updated],
                ['Páginas ignoradas', count($result->skipped)],
                ['Erros', count($result->errors)],
                ['Modo dry-run?', $result->dryRun ? 'sim' : 'não'],
            ],
        );

        if (! empty($result->newHeroSlugs)) {
            $this->newLine();
            $this->info('Novos heróis (slugs):');
            $this->line('  '.implode(', ', $result->newHeroSlugs));
        }

        if (! empty($result->skipped)) {
            $this->newLine();
            $this->comment('Páginas ignoradas (até 20):');
            foreach (array_slice($result->skipped, 0, 20) as $line) {
                $this->line('  · '.$line);
            }
        }

        if (! empty($result->errors)) {
            $this->newLine();
            $this->error('Erros (até 20):');
            foreach (array_slice($result->errors, 0, 20) as $line) {
                $this->line('  ! '.$line);
            }
        }
    }
}
