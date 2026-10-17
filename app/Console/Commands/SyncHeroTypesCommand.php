<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Services\HeroCatalogSync\FandomWikiClient;
use App\Services\HeroCatalogSync\WikitextHeroParser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SyncHeroTypesCommand extends Command
{
    protected $signature = 'heroes:sync-types
        {--force : Re-extrai mesmo para heróis que já têm type preenchido}
        {--limit= : Limita o nº de heróis processados}
        {--slug= : Processa apenas o herói com este slug}';

    protected $description = 'Popula heroes.type (Strength/Intelligence/Agility) a partir do infobox da Fandom.';

    public function handle(FandomWikiClient $client, WikitextHeroParser $parser): int
    {
        $query = Hero::query()->where('is_active', true);

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        } elseif (! $this->option('force')) {
            $query->whereNull('type');
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $heroes = $query->get();
        $total = $heroes->count();

        if ($total === 0) {
            $this->info('Nenhum herói para atualizar.');

            return self::SUCCESS;
        }

        $this->info("Atualizando type em {$total} heróis...");

        $updated = 0;
        $unchanged = 0;
        $missing = 0;
        $errors = 0;

        foreach ($heroes as $i => $hero) {
            $title = $this->slugToWikiTitle($hero);

            try {
                $wt = $client->fetchWikitext($title);
                if (! $wt) {
                    $this->line("  <fg=yellow>?</> [{$i}/{$total}] {$hero->slug} (sem wikitext em '{$title}')");
                    $missing++;

                    continue;
                }

                $parsed = $parser->parse($wt);
                $type = $parsed['type'] ?? null;

                if ($type === null) {
                    $this->line("  <fg=yellow>-</> [{$i}/{$total}] {$hero->slug} (campo type ausente na wiki)");
                    $missing++;

                    continue;
                }

                if ($type === $hero->type) {
                    $this->line("  <fg=gray>=</> [{$i}/{$total}] {$hero->slug} (já = {$type})");
                    $unchanged++;

                    continue;
                }

                $hero->update(['type' => $type]);
                $this->line("  <fg=green>+</> [{$i}/{$total}] {$hero->slug} → {$type}");
                $updated++;
            } catch (Throwable $e) {
                $errors++;
                $this->line("  <fg=red>!</> [{$i}/{$total}] {$hero->slug}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Concluído. Atualizados: {$updated}, sem mudança: {$unchanged}, sem dado: {$missing}, erros: {$errors}.");

        return self::SUCCESS;
    }

    private function slugToWikiTitle(Hero $hero): string
    {
        if ($hero->source_url) {
            $parts = explode('/wiki/', $hero->source_url);
            if (count($parts) === 2) {
                return rawurldecode(str_replace('_', ' ', $parts[1]));
            }
        }

        return Str::title(str_replace('-', ' ', $hero->slug));
    }
}
