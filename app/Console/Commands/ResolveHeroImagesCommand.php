<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Services\HeroCatalogSync\FandomWikiClient;
use Illuminate\Console\Command;

class ResolveHeroImagesCommand extends Command
{
    protected $signature = 'heroes:resolve-images
        {--size=400 : Tamanho da thumbnail em pixels (largura)}
        {--force : Atualiza inclusive heróis cujo portrait_url já é CDN (static.wikia.nocookie.net)}
        {--dry-run : Apenas relata, não persiste}';

    protected $description = 'Resolve as URLs reais das thumbnails dos heróis via API pageimages do Fandom (substitui Special:Filepath por static.wikia.nocookie.net).';

    public function handle(FandomWikiClient $client): int
    {
        $size = (int) $this->option('size');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $query = Hero::query()->where('is_active', true)->whereNotNull('source_url');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('portrait_url')
                    ->orWhere('portrait_url', 'not like', '%static.wikia.nocookie.net%');
            });
        }

        $heroes = $query->get(['id', 'slug', 'name', 'source_url', 'portrait_url']);

        if ($heroes->isEmpty()) {
            $this->info('Nada a fazer — todos os heróis ativos já têm thumbnail CDN.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Resolvendo thumbnails para %d heróis (size=%d, dry-run=%s)...', $heroes->count(), $size, $dryRun ? 'sim' : 'não'));

        $titlesByHeroId = [];
        foreach ($heroes as $hero) {
            $title = $this->extractTitleFromSourceUrl($hero->source_url);
            if ($title !== null) {
                $titlesByHeroId[$hero->id] = $title;
            }
        }

        if (empty($titlesByHeroId)) {
            $this->error('Não foi possível extrair page titles das source_url.');

            return self::FAILURE;
        }

        $allTitles = array_values($titlesByHeroId);
        $start = microtime(true);

        $this->info(sprintf('Chamando pageimages em %d batches de até 50...', (int) ceil(count($allTitles) / 50)));

        $thumbnails = $client->fetchPageThumbnails($allTitles, $size);

        $updated = 0;
        $missing = [];

        foreach ($titlesByHeroId as $heroId => $title) {
            $url = $thumbnails[$title] ?? null;

            if ($url === null) {
                $missing[] = $title;

                continue;
            }

            if (! $dryRun) {
                Hero::where('id', $heroId)->update(['portrait_url' => $url]);
            }
            $updated++;
        }

        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info("Concluído em {$elapsed}s.");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Heróis avaliados', count($titlesByHeroId)],
                ['Thumbnails atualizadas', $updated],
                ['Sem thumbnail na wiki', count($missing)],
                ['Modo dry-run?', $dryRun ? 'sim' : 'não'],
            ],
        );

        if (! empty($missing)) {
            $this->newLine();
            $this->comment('Heróis sem thumbnail na wiki (até 30):');
            foreach (array_slice($missing, 0, 30) as $title) {
                $this->line('  · '.$title);
            }
        }

        return self::SUCCESS;
    }

    private function extractTitleFromSourceUrl(string $sourceUrl): ?string
    {
        $path = parse_url($sourceUrl, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $segments = explode('/', trim($path, '/'));
        $last = end($segments);

        if (! is_string($last) || $last === '') {
            return null;
        }

        return str_replace('_', ' ', rawurldecode($last));
    }
}
