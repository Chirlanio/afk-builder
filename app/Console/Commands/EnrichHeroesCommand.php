<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Services\HeroCatalogSync\FandomSyncService;
use App\Services\HeroCatalogSync\FandomWikiClient;
use App\Services\HeroCatalogSync\WikitextHeroParser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class EnrichHeroesCommand extends Command
{
    protected $signature = 'heroes:enrich
        {--force : Re-extrai mesmo se o herói já tem lore e abilities}
        {--limit= : Limita o nº de heróis processados}
        {--slug= : Processa apenas o herói com este slug}';

    protected $description = 'Popula lore (Background) e abilities a partir da wiki para heróis já importados.';

    public function handle(
        FandomWikiClient $client,
        WikitextHeroParser $parser,
        FandomSyncService $sync,
    ): int {
        $query = Hero::query()->where('is_active', true);

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        } elseif (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('lore')->orWhereNull('type')->orWhereDoesntHave('abilities');
            });
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $heroes = $query->get();
        $total = $heroes->count();

        if ($total === 0) {
            $this->info('Nenhum herói para enriquecer.');

            return self::SUCCESS;
        }

        $this->info("Enriquecendo {$total} heróis...");

        $enrichedLore = 0;
        $enrichedAbilities = 0;
        $enrichedType = 0;
        $errors = 0;

        foreach ($heroes as $i => $hero) {
            $title = $this->slugToWikiTitle($hero);

            try {
                $wt = $client->fetchWikitext($title);
                if (! $wt) {
                    $this->line("  <fg=yellow>?</> [{$i}/{$total}] {$hero->slug} (sem wikitext em '{$title}')");

                    continue;
                }

                $parsed = $parser->parse($wt);
                $lore = $parser->parseLore($wt);
                $abilities = $parser->parseAbilities($wt);

                $updates = [];
                if ($lore !== $hero->lore) {
                    $updates['lore'] = $lore;
                }
                if ($parsed && $parsed['type'] !== $hero->type) {
                    $updates['type'] = $parsed['type'];
                }
                if ($parsed && ! empty($parsed['role_label']) && $parsed['role_label'] !== $hero->role_label) {
                    $updates['role_label'] = $parsed['role_label'];
                }

                if (! empty($updates)) {
                    $hero->update($updates);
                    if (array_key_exists('lore', $updates)) {
                        $enrichedLore++;
                    }
                    if (array_key_exists('type', $updates)) {
                        $enrichedType++;
                    }
                }

                if (! empty($abilities)) {
                    $sync->syncAbilities($hero, $abilities);
                    $enrichedAbilities++;
                }

                $marker = (! empty($updates) || ! empty($abilities)) ? '<fg=green>+</>' : '<fg=gray>=</>';
                $typeLabel = $parsed['type'] ?? '?';
                $this->line("  {$marker} [{$i}/{$total}] {$hero->slug} (lore: ".strlen($lore ?? '').' chars, abilities: '.count($abilities).", type: {$typeLabel})");
            } catch (Throwable $e) {
                $errors++;
                $this->line("  <fg=red>!</> [{$i}/{$total}] {$hero->slug}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Concluído. Lore: {$enrichedLore}, abilities: {$enrichedAbilities}, type: {$enrichedType}, erros: {$errors}.");

        return self::SUCCESS;
    }

    /**
     * Reverte slug → título da wiki. Heróis foram importados com slug = Str::slug(pageTitle),
     * então recuperamos a partir do source_url (que tem o título original).
     */
    private function slugToWikiTitle(Hero $hero): string
    {
        if ($hero->source_url) {
            $parts = explode('/wiki/', $hero->source_url);
            if (count($parts) === 2) {
                return rawurldecode(str_replace('_', ' ', $parts[1]));
            }
        }

        // Fallback: capitaliza palavras do slug.
        return Str::title(str_replace('-', ' ', $hero->slug));
    }
}
