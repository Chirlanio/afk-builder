<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Models\HeroRating;
use Composer\CaBundle\CaBundle;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncHeroRatingsCommand extends Command
{
    protected $signature = 'heroes:sync-ratings
        {--dry-run : Mostra apenas o que seria persistido}
        {--source=https://afk.guide/afk-arena-tier-list/ : URL da página de tier list}';

    protected $description = 'Sincroniza tier ratings (S+/S/A/B/C/D/F) por modo (PvP/PvE/Labyrinth/...) e estágio (early/mid/end) a partir do afk.guide.';

    private const HEADER_TO_MODE = [
        'overall' => HeroRating::MODE_OVERALL,
        'pvp' => HeroRating::MODE_PVP,
        'pve' => HeroRating::MODE_PVE,
        'labyrinth' => HeroRating::MODE_LABYRINTH,
        'twisted realm' => HeroRating::MODE_TWISTED_REALM,
        'guild hunt' => HeroRating::MODE_TWISTED_REALM,
        'temporal rift' => HeroRating::MODE_TEMPORAL_RIFT,
        'abyssal expedition' => HeroRating::MODE_ABYSSAL_EXPEDITION,
    ];

    public function handle(): int
    {
        $url = (string) $this->option('source');
        $this->info("Buscando HTML de {$url}...");

        try {
            $html = Http::withHeaders(['User-Agent' => 'afk-builder/1.0 (sync; +https://github.com/Chirlanio/afk-builder)'])
                ->withOptions(['verify' => CaBundle::getSystemCaRootBundlePath()])
                ->timeout(30)
                ->retry(2, 1000)
                ->get($url)
                ->throw()
                ->body();
        } catch (\Throwable $e) {
            $this->error("Falha ao buscar página: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('HTML baixado ('.number_format(strlen($html)).' bytes). Parsificando...');

        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);

        $tables = $xpath->query("//table[contains(@class, 'ninja_footable')]");
        if (! $tables || $tables->length === 0) {
            $this->error('Nenhuma tabela ninja_footable encontrada na página.');

            return self::FAILURE;
        }

        $batch = now()->format('YmdHis');
        $totals = ['parsed' => 0, 'matched' => 0, 'skipped' => 0, 'persisted' => 0];
        $unmatched = [];

        foreach ($tables as $tableIndex => $table) {
            /** @var DOMElement $table */
            $stage = $this->stageFromTable($xpath, $table, $tableIndex);
            $this->newLine();
            $this->line('<fg=cyan>Tabela '.($tableIndex + 1)."</> → stage <fg=yellow>{$stage}</>");

            $headers = $this->extractHeaders($xpath, $table);
            $modeColumns = [];
            foreach ($headers as $idx => $headerText) {
                $key = strtolower(trim($headerText));
                if (isset(self::HEADER_TO_MODE[$key])) {
                    $modeColumns[$idx] = self::HEADER_TO_MODE[$key];
                }
            }
            if ($modeColumns === []) {
                $this->warn('  → nenhuma coluna de rating reconhecida, pulando tabela.');

                continue;
            }

            $heroNameColumn = null;
            foreach ($headers as $idx => $headerText) {
                if (strtolower(trim($headerText)) === 'hero') {
                    $heroNameColumn = $idx;
                    break;
                }
            }
            if ($heroNameColumn === null) {
                $this->warn('  → coluna "Hero" não encontrada, pulando tabela.');

                continue;
            }

            $rows = $xpath->query('.//tbody/tr', $table);
            foreach ($rows as $row) {
                /** @var DOMElement $row */
                $cells = $xpath->query('./td', $row);
                if (! $cells || $cells->length <= $heroNameColumn) {
                    continue;
                }
                $totals['parsed']++;

                $heroNameRaw = trim($cells->item($heroNameColumn)->textContent);
                $heroName = $this->normalizeName($heroNameRaw);
                if ($heroName === '') {
                    $totals['skipped']++;

                    continue;
                }

                $hero = $this->resolveHero($heroName);
                if (! $hero) {
                    $totals['skipped']++;
                    $unmatched[$heroName] = ($unmatched[$heroName] ?? 0) + 1;

                    continue;
                }
                $totals['matched']++;

                foreach ($modeColumns as $colIdx => $mode) {
                    if ($colIdx >= $cells->length) {
                        continue;
                    }
                    $tierRaw = trim($cells->item($colIdx)->textContent);
                    $tier = $this->normalizeTier($tierRaw);
                    if ($tier === null) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("  <fg=gray>[dry]</> {$hero->slug} · {$mode}/{$stage} = {$tier}");

                        continue;
                    }

                    HeroRating::updateOrCreate(
                        ['hero_id' => $hero->id, 'mode' => $mode, 'stage' => $stage, 'source' => 'afk.guide'],
                        ['tier' => $tier, 'source_url' => $url, 'sync_batch' => $batch],
                    );
                    $totals['persisted']++;
                }
            }
        }

        $this->newLine();
        $this->info("Concluído. Linhas parsificadas: {$totals['parsed']}, heróis matched: {$totals['matched']}, skipped: {$totals['skipped']}, ratings persistidos: {$totals['persisted']}.");

        if ($unmatched !== []) {
            $this->newLine();
            $this->warn('Heróis não encontrados no banco (top 20):');
            arsort($unmatched);
            foreach (array_slice($unmatched, 0, 20, true) as $name => $count) {
                $this->line("  - {$name} ({$count}x)");
            }
        }

        return self::SUCCESS;
    }

    private function stageFromTable(DOMXPath $xpath, DOMElement $table, int $index): string
    {
        $caption = $xpath->query('.//caption', $table);
        $captionText = '';
        if ($caption && $caption->length > 0) {
            $captionText = strtolower(trim($caption->item(0)->textContent));
        }

        $aria = strtolower((string) $table->getAttribute('aria-label'));

        if (str_contains($captionText, 'beginning') || str_contains($captionText, 'chapter 1') || str_contains($aria, 'early')) {
            return HeroRating::STAGE_EARLY;
        }
        if (preg_match('/chapter\s*30\s*to\s*chapter\s*35/', $captionText)) {
            return HeroRating::STAGE_MID;
        }
        if (str_contains($captionText, 'late') || str_contains($aria, 'late') || str_contains($aria, 'end')) {
            return HeroRating::STAGE_END;
        }

        return [HeroRating::STAGE_EARLY, HeroRating::STAGE_MID, HeroRating::STAGE_END][$index] ?? HeroRating::STAGE_END;
    }

    /**
     * @return string[]
     */
    private function extractHeaders(DOMXPath $xpath, DOMElement $table): array
    {
        $ths = $xpath->query('.//thead/tr/th', $table);
        $headers = [];
        if (! $ths) {
            return $headers;
        }
        foreach ($ths as $idx => $th) {
            $headers[$idx] = trim($th->textContent);
        }

        return $headers;
    }

    private function normalizeName(string $raw): string
    {
        $clean = preg_replace('/\s+/', ' ', html_entity_decode($raw, ENT_QUOTES, 'UTF-8')) ?? $raw;

        return trim($clean);
    }

    private function normalizeTier(string $raw): ?string
    {
        $upper = strtoupper(trim($raw));
        if ($upper === '' || $upper === '-' || $upper === '—') {
            return null;
        }
        $upper = str_replace(['＋', '➕'], '+', $upper);

        return in_array($upper, HeroRating::TIERS, true) ? $upper : null;
    }

    private function resolveHero(string $name): ?Hero
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            return null;
        }

        $hero = Hero::where('slug', $slug)->first();
        if ($hero) {
            return $hero;
        }

        // "Awakened X" → tenta achar X marcado como awakened (se a flag existir)
        if (str_starts_with(strtolower($name), 'awakened ')) {
            $baseName = trim(substr($name, 9));
            $baseSlug = Str::slug($baseName);
            $hero = Hero::where('slug', $baseSlug)->first();
            if ($hero) {
                return $hero;
            }
        }

        // Fallback: match case-insensitive por name (cobre "Elijah & Lailah" se slug diverge)
        return Hero::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }
}
