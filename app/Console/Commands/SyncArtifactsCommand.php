<?php

namespace App\Console\Commands;

use App\Models\Artifact;
use App\Models\HeroClass;
use App\Services\HeroCatalogSync\FandomWikiClient;
use App\Services\HeroCatalogSync\WikitextHeroParser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncArtifactsCommand extends Command
{
    protected $signature = 'artifacts:sync
        {--dry-run : Não persiste, só mostra o que seria criado/atualizado}';

    protected $description = 'Sincroniza catálogo de artefatos (Dura\'s + Class) da página Artifacts da Fandom.';

    private const PAGE_TITLE = 'Artifacts';

    public function handle(FandomWikiClient $client, WikitextHeroParser $parser): int
    {
        $this->info("Buscando wikitext de '" . self::PAGE_TITLE . "'...");

        $wikitext = $client->fetchWikitext(self::PAGE_TITLE);
        if (! $wikitext) {
            $this->error('Não foi possível obter wikitext da página.');
            return self::FAILURE;
        }

        $parsed = $parser->parseArtifactsIndex($wikitext);
        $duraCount = count($parsed['dura']);
        $classCount = count($parsed['class']);
        $this->info("Parseados: {$duraCount} Dura's + {$classCount} Class.");

        if ($duraCount === 0 && $classCount === 0) {
            $this->error('Nenhum artefato encontrado — verificar parser ou estrutura da wiki.');
            return self::FAILURE;
        }

        $classMap = HeroClass::pluck('id', 'code')->all();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $process = function (array $entries, string $kind, int $maxStars) use (&$created, &$updated, &$skipped, $classMap) {
            foreach ($entries as $a) {
                $slug = Str::slug($a['name']);
                if ($slug === '') {
                    $skipped++;
                    continue;
                }

                $heroClassId = null;
                if ($kind === Artifact::KIND_CLASS) {
                    if (! $a['class_code'] || ! isset($classMap[$a['class_code']])) {
                        $this->line("  <fg=yellow>?</> {$a['name']} (classe inválida: {$a['class_code']})");
                        $skipped++;
                        continue;
                    }
                    $heroClassId = $classMap[$a['class_code']];
                }

                $portraitUrl = $a['image_filename']
                    ? 'https://afk-arena.fandom.com/wiki/Special:Filepath/' . rawurlencode($a['image_filename'])
                    : null;

                $sourceUrl = 'https://afk-arena.fandom.com/wiki/Artifacts#' . rawurlencode(str_replace(' ', '_', $a['name']));

                $payload = [
                    'name' => $a['name'],
                    'kind' => $kind,
                    'hero_class_id' => $heroClassId,
                    'description' => $a['description'] ?: null,
                    'attributes' => $a['attributes'],
                    'powers' => $a['powers'],
                    'obtained_in' => $a['obtained_in'] ?: null,
                    'image_filename' => $a['image_filename'],
                    'portrait_url' => $portraitUrl,
                    'source_url' => $sourceUrl,
                    'max_stars' => $maxStars,
                    'is_active' => true,
                ];

                if ($this->option('dry-run')) {
                    $existing = Artifact::where('slug', $slug)->exists();
                    $this->line("  [dry] " . ($existing ? '~' : '+') . " {$slug} ({$kind})");
                    continue;
                }

                $existing = Artifact::where('slug', $slug)->first();

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                    $this->line("  <fg=gray>~</> {$slug} ({$kind})");
                } else {
                    Artifact::create([...$payload, 'slug' => $slug]);
                    $created++;
                    $this->line("  <fg=green>+</> {$slug} ({$kind})");
                }
            }
        };

        $this->newLine();
        $this->info("Dura's Artifacts (max 5★):");
        $process($parsed['dura'], Artifact::KIND_DURA, 5);

        $this->newLine();
        $this->info('Class Artifacts (max 3★):');
        $process($parsed['class'], Artifact::KIND_CLASS, 3);

        $this->newLine();
        $this->info("Concluído. Criados: {$created}, atualizados: {$updated}, pulados: {$skipped}.");

        return self::SUCCESS;
    }
}
