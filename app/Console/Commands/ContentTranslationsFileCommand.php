<?php

namespace App\Console\Commands;

use App\Models\Guide;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroFurnitureAbility;
use App\Services\GoogleFreeTranslator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * EN→PT-BR translation in three steps, through a JSONL file:
 *
 *   export    (server)    untranslated texts → file
 *   translate (elsewhere) file → file with a `translation` on each line
 *   import    (server)    writes the translations into the *_pt fields
 *
 * Why it exists: on Hostinger, Google answers HTTP 429 to every request made by
 * PHP (the TLS fingerprint of the server's PHP libcurl is treated as a bot — the
 * command-line curl on the same server gets through, but PHP cannot call it:
 * exec/proc_open are blocked). Other machines are accepted. Where it works,
 * `content:translate` is still the direct path.
 */
class ContentTranslationsFileCommand extends Command
{
    protected $signature = 'content:translations {action : export | translate | import} {file : Path to the JSONL file}
        {--output= : (translate) output file; default: <file>.pt.jsonl}
        {--delay=1500 : (translate) pause in ms between translations — Google throttles the IP after ~90 in a row}';

    protected $description = 'Export, translate or import EN→PT-BR content through a file (when the server cannot reach Google)';

    /** Waits (s) after an HTTP 429 before retrying the same text. */
    private const RATE_LIMIT_WAITS = [30, 60, 120, 240];

    /** Model → [English field => translated field]. Also the allowlist for import. */
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
        $this->info("Exported {$rows} untranslated text(s) to {$file}.");

        return self::SUCCESS;
    }

    private function translate(string $file, string $output): int
    {
        $translator = new GoogleFreeTranslator;

        // Resume where it stopped: lines already in the output are not redone.
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

        $delayMs = max(0, (int) $this->option('delay'));

        foreach ($lines as $i => $line) {
            $row = json_decode($line, true);
            $key = "{$row['type']}:{$row['id']}:{$row['field']}";
            if (isset($done[$key])) {
                continue;
            }

            // HTTP 429 = Google throttling the IP: wait and retry the SAME text
            // (30s, 1, 2 and 4 min) instead of skipping through the whole file.
            foreach ([0, ...self::RATE_LIMIT_WAITS] as $attempt => $wait) {
                if ($wait > 0) {
                    $this->warn("  … Google rate limit; waiting {$wait}s before retrying {$key}");
                    Sleep::for($wait)->seconds();
                }

                try {
                    $row['translation'] = $translator->translate($row['text']);
                    fwrite($out, json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL);
                    $translated++;
                    $this->line('  ['.($i + 1).'/'.count($lines)."] {$key}");
                    Sleep::for($delayMs)->milliseconds();
                    break;
                } catch (Throwable $e) {
                    $rateLimited = str_contains($e->getMessage(), '429');
                    if ($rateLimited && $attempt < count(self::RATE_LIMIT_WAITS)) {
                        continue;
                    }
                    $failures++;
                    $this->warn("  ! {$key}: {$e->getMessage()}");
                    break;
                }
            }

            if ($failures >= 20) {
                $this->error('20 failures — stopped. Run again to resume.');
                break;
            }
        }

        fclose($out);
        $this->info("Translated {$translated} now; ".count($done).' were already done. Output: '.$output);

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

            // Only fills empty fields — never overwrites an existing translation.
            if ($record === null || $record->{$target} !== null) {
                $skipped++;

                continue;
            }

            $record->forceFill([$target => $translation])->save();
            $saved++;
        }

        $this->info("Saved {$saved} translation(s); skipped {$skipped} (already translated or invalid).");

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Invalid action. Use export, translate or import.');

        return self::INVALID;
    }
}
