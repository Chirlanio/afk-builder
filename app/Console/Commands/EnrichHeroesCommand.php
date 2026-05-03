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
                $q->whereNull('lore')->orWhereDoesntHave('abilities');
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
        $errors = 0;

        foreach ($heroes as $i => $hero) {
            $title = $this->slugToWikiTitle($hero);

            try {
                $wt = $client->fetchWikitext($title);
                if (! $wt) {
                    $this->line("  <fg=yellow>?</> [{$i}/{$total}] {$hero->slug} (sem wikitext em '{$title}')");
                    continue;
                }

                $lore = $parser->parseLore($wt);
                $abilities = $parser->parseAbilities($wt);

                $loreChanged = $lore !== $hero->lore;
                if ($loreChanged) {
                    $hero->update(['lore' => $lore]);
                    $enrichedLore++;
                }

                if (! empty($abilities)) {
                    $sync->syncAbilities($hero, $abilities);
                    $enrichedAbilities++;
                }

                $marker = ($loreChanged || ! empty($abilities)) ? '<fg=green>+</>' : '<fg=gray>=</>';
                $this->line("  {$marker} [{$i}/{$total}] {$hero->slug} (lore: ".strlen($lore ?? '').' chars, abilities: '.count($abilities).')');
            } catch (Throwable $e) {
                $errors++;
                $this->line("  <fg=red>!</> [{$i}/{$total}] {$hero->slug}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Concluído. Lore atualizada: {$enrichedLore}, abilities atualizadas: {$enrichedAbilities}, erros: {$errors}.");

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
