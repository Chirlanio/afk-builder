<?php

namespace App\Console\Commands;

use App\Models\Guide;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroFurnitureAbility;
use App\Services\GoogleFreeTranslator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Tradução EN→PT-BR em três etapas, por arquivo JSONL:
 *
 *   export    (servidor)  textos ainda sem tradução → arquivo
 *   translate (fora dele) arquivo → arquivo com `translation` em cada linha
 *   import    (servidor)  grava as traduções nos campos *_pt
 *
 * Por que existe: na Hostinger o Google responde HTTP 429 a qualquer requisição
 * feita pelo PHP (a "impressão digital" TLS do libcurl do PHP de lá é tratada
 * como robô — o curl de linha de comando do mesmo servidor passa, mas o PHP não
 * pode chamá-lo: exec/proc_open são bloqueados). De outra máquina o Google
 * aceita. Nas máquinas onde funciona, `content:translate` continua sendo o
 * caminho direto.
 */
class ContentTranslationsFileCommand extends Command
{
    protected $signature = 'content:translations {action : export | translate | import} {file : Caminho do arquivo JSONL}
        {--output= : (translate) arquivo de saída; padrão: <file>.pt.jsonl}';

    protected $description = 'Exporta, traduz ou importa conteúdo EN→PT-BR via arquivo (quando o servidor não alcança o Google)';

    /** Modelo → [campo em inglês => campo traduzido]. Também é a lista do que o import aceita. */
    private const FIELDS = [
        'hero' => [Hero::class, ['lore' => 'lore_pt']],
        'ability' => [HeroAbility::class, ['name' => 'name_pt', 'description' => 'description_pt']],
        'furniture' => [HeroFurnitureAbility::class, ['name' => 'name_pt', 'f3_description' => 'f3_description_pt', 'f9_description' => 'f9_description_pt']],
        'guide' => [Guide::class, ['title' => 'title_pt', 'summary' => 'summary_pt', 'content' => 'content_pt']],
    ];

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'export' => $this->export($this->argument('file')),
            'translate' => $this->translate($this->argument('file'), $this->option('output') ?: preg_replace('/\.jsonl$/', '', $this->argument('file')).'.pt.jsonl'),
            'import' => $this->import($this->argument('file')),
            default => $this->invalidAction(),
        };
    }

    private function export(string $file): int
    {
        $out = fopen($file, 'w');
        $rows = 0;

        foreach (self::FIELDS as $type => [$model, $fields]) {
            foreach ($model::query()->get() as $record) {
                foreach ($fields as $source => $target) {
                    $text = $record->{$source};
                    if (is_string($text) && trim($text) !== '' && $record->{$target} === null) {
                        fwrite($out, json_encode(['type' => $type, 'id' => $record->getKey(), 'field' => $source, 'text' => $text], JSON_UNESCAPED_UNICODE).PHP_EOL);
                        $rows++;
                    }
                }
            }
        }

        fclose($out);
        $this->info("{$rows} texto(s) sem tradução exportado(s) para {$file}.");

        return self::SUCCESS;
    }

    private function translate(string $file, string $output): int
    {
        $translator = new GoogleFreeTranslator;

        // Retoma de onde parou: linhas já traduzidas na saída não são refeitas.
        $done = [];
        if (is_file($output)) {
            foreach (file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $row = json_decode($line, true);
                $done["{$row['type']}:{$row['id']}:{$row['field']}"] = true;
            }
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $out = fopen($output, 'a');
        $translated = 0;
        $failures = 0;

        foreach ($lines as $i => $line) {
            $row = json_decode($line, true);
            $key = "{$row['type']}:{$row['id']}:{$row['field']}";
            if (isset($done[$key])) {
                continue;
            }

            try {
                $row['translation'] = $translator->translate($row['text']);
                fwrite($out, json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL);
                $translated++;
                $this->line('  ['.($i + 1).'/'.count($lines)."] {$key}");
                usleep(200_000);
            } catch (Throwable $e) {
                $failures++;
                $this->warn("  ! {$key}: {$e->getMessage()}");
                if ($failures >= 20) {
                    $this->error('20 falhas — interrompido. Rode de novo para retomar.');
                    break;
                }
            }
        }

        fclose($out);
        $this->info("{$translated} traduzido(s) agora; ".count($done).' já estavam prontos. Saída: '.$output);

        return $failures >= 20 ? self::FAILURE : self::SUCCESS;
    }

    private function import(string $file): int
    {
        $saved = 0;
        $skipped = 0;

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $row = json_decode($line, true);
            [$model, $fields] = self::FIELDS[$row['type'] ?? ''] ?? [null, []];
            $target = $fields[$row['field'] ?? ''] ?? null;
            $translation = $row['translation'] ?? null;

            /** @var Model|null $record */
            $record = $model && $target && is_string($translation) && trim($translation) !== '' ? $model::find($row['id']) : null;

            // Só preenche o que ainda está vazio — não sobrescreve tradução existente.
            if ($record === null || $record->{$target} !== null) {
                $skipped++;

                continue;
            }

            $record->forceFill([$target => $translation])->save();
            $saved++;
        }

        $this->info("{$saved} tradução(ões) gravada(s); {$skipped} ignorada(s) (já traduzidas ou inválidas).");

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Ação inválida. Use export, translate ou import.');

        return self::INVALID;
    }
}
