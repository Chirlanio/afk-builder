<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Models\HeroRating;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class SyncHeroRatingsAllClashCommand extends Command
{
    protected $signature = 'heroes:sync-ratings-allclash
        {--dry-run : Mostra apenas o que seria persistido}
        {--source=https://www.allclash.com/the-best-heroes-in-afk-arena-tier-list/ : URL da página de tier list}
        {--from-file= : Caminho para HTML local (bypassa Cloudflare se fetch falhar)}';

    protected $description = 'Sincroniza tier ratings (S+/S/A+/A/B+/B/C+/C/D+/D/F) overall do allclash.com via curl (Cloudflare bloqueia HTTP client PHP). Use --from-file=path se curl também falhar.';

    private const TIER_CODE_MAP = [
        'ss' => 'S+',
        's' => 'S',
        'as' => 'A+',
        'a' => 'A',
        'bs' => 'B+',
        'b' => 'B',
        'cs' => 'C+',
        'c' => 'C',
        'ds' => 'D+',
        'd' => 'D',
        'f' => 'F',
    ];

    public function handle(): int
    {
        $url = (string) $this->option('source');
        $fromFile = (string) ($this->option('from-file') ?? '');

        if ($fromFile !== '') {
            if (! is_readable($fromFile)) {
                $this->error("Arquivo não legível: {$fromFile}");
                return self::FAILURE;
            }
            $html = (string) file_get_contents($fromFile);
            $this->info("HTML lido de {$fromFile} (".number_format(strlen($html))." bytes).");
        } else {
            $this->info("Buscando HTML de {$url} via curl...");
            $result = Process::timeout(30)->run([
                'curl', '-sL', '--fail',
                '-A', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                $url,
            ]);
            if (! $result->successful()) {
                $this->error("curl falhou (exit {$result->exitCode()}). Stderr: ".trim($result->errorOutput()));
                $this->comment('Dica: faça download manual e use --from-file=caminho.html');
                return self::FAILURE;
            }
            $html = $result->output();
            $this->info('HTML baixado ('.number_format(strlen($html)).' bytes). Parsificando...');
        }

        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);

        $items = $xpath->query("//div[contains(@class, 'tieritem') and contains(@class, 'afkarena')]");
        if (! $items || $items->length === 0) {
            $this->error('Nenhum tieritem encontrado na página.');
            return self::FAILURE;
        }

        $this->info("Encontrados {$items->length} itens. Processando...");

        $batch = now()->format('YmdHis');
        $totals = ['parsed' => 0, 'matched' => 0, 'skipped' => 0, 'persisted' => 0];
        $unmatched = [];

        foreach ($items as $item) {
            /** @var DOMElement $item */
            $totals['parsed']++;

            $idAttr = $item->getAttribute('id');
            $classAttr = $item->getAttribute('class');

            if ($idAttr === '' || ! preg_match('/tiercode-([a-z]+)/', $classAttr, $tm)) {
                $totals['skipped']++;
                continue;
            }

            $code = $tm[1];
            if (! isset(self::TIER_CODE_MAP[$code])) {
                // Tiercode não é de tier de herói (newsletter, pro, free, best, new, etc)
                $totals['skipped']++;
                continue;
            }
            $tier = self::TIER_CODE_MAP[$code];

            $hero = $this->resolveHero($idAttr);
            if (! $hero) {
                $totals['skipped']++;
                $unmatched[$idAttr] = ($unmatched[$idAttr] ?? 0) + 1;
                continue;
            }
            $totals['matched']++;

            if ($this->option('dry-run')) {
                $this->line("  <fg=gray>[dry]</> {$hero->slug} · overall/all = {$tier}");
                continue;
            }

            HeroRating::updateOrCreate(
                ['hero_id' => $hero->id, 'mode' => HeroRating::MODE_OVERALL, 'stage' => HeroRating::STAGE_ALL, 'source' => 'allclash'],
                ['tier' => $tier, 'source_url' => $url, 'sync_batch' => $batch],
            );
            $totals['persisted']++;
        }

        $this->newLine();
        $this->info("Concluído. Itens parsificados: {$totals['parsed']}, heróis matched: {$totals['matched']}, skipped: {$totals['skipped']}, ratings persistidos: {$totals['persisted']}.");

        if ($unmatched !== []) {
            $this->newLine();
            $this->warn('Heróis (id allclash) não encontrados no banco (top 20):');
            arsort($unmatched);
            foreach (array_slice($unmatched, 0, 20, true) as $name => $count) {
                $this->line("  - {$name} ({$count}x)");
            }
        }

        return self::SUCCESS;
    }

    private function resolveHero(string $allClashId): ?Hero
    {
        // allclash usa id="awakened lyca" (lowercased + spaces); às vezes "elijah & lailah (twins)"
        $name = trim($allClashId);
        if ($name === '') {
            return null;
        }

        $variants = [$name];
        // Remove parêntese final tipo "(twins)" ou "(dragonic)"
        $stripped = trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $name));
        if ($stripped !== $name && $stripped !== '') {
            $variants[] = $stripped;
        }
        if (str_starts_with(strtolower($name), 'awakened ')) {
            $variants[] = trim(substr($name, 9));
        }

        foreach ($variants as $v) {
            $slug = Str::slug($v);
            if ($slug === '') {
                continue;
            }
            $hero = Hero::where('slug', $slug)->first();
            if ($hero) {
                return $hero;
            }
            $hero = Hero::whereRaw('LOWER(name) = ?', [strtolower($v)])->first();
            if ($hero) {
                return $hero;
            }
        }
        return null;
    }
}
