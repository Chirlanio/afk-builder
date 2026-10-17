<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Models\HeroFurnitureAbility;
use App\Services\HeroCatalogSync\FandomWikiClient;
use App\Services\HeroCatalogSync\WikitextHeroParser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncFurnitureCommand extends Command
{
    protected $signature = 'heroes:sync-furniture
        {--dry-run : Não escreve no banco, apenas relata}';

    protected $description = 'Sincroniza habilidades de móveis (F3/F9) da página master "Hero Furniture Abilities" da Fandom.';

    private const WIKI_PAGE = 'Hero Furniture Abilities';

    public function handle(FandomWikiClient $client, WikitextHeroParser $parser): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma alteração será persistida.');
        }

        $this->info('Buscando "'.self::WIKI_PAGE.'" da Fandom...');
        $wikitext = $client->fetchWikitext(self::WIKI_PAGE);

        if ($wikitext === null) {
            $this->error('Página não encontrada.');

            return self::FAILURE;
        }

        $entries = $parser->parseFurnitureAbilitiesIndex($wikitext);
        $this->info(count($entries).' habilidades de móvel extraídas da página.');

        $allHeroes = Hero::query()
            ->where('is_active', true)
            ->get(['id', 'slug', 'name']);

        $heroesByName = $allHeroes->keyBy(fn (Hero $h) => $this->normalizeName($h->name));
        $heroesBySlug = $allHeroes->keyBy('slug');

        $created = 0;
        $updated = 0;
        $missing = [];

        foreach ($entries as $entry) {
            $hero = $heroesByName->get($this->normalizeName($entry['hero_name']));

            // Fallback: tenta por slug (kebab-case do nome wiki).
            if (! $hero) {
                $slug = Str::slug($entry['hero_name']);
                $hero = $heroesBySlug->get($slug);
            }

            if (! $hero) {
                $missing[] = $entry['hero_name'];

                continue;
            }

            if ($dryRun) {
                $this->line("  <fg=cyan>~</> {$entry['hero_name']} → {$entry['ability_name']}");

                continue;
            }

            $existing = HeroFurnitureAbility::where('hero_id', $hero->id)->first();
            $payload = [
                'hero_id' => $hero->id,
                'name' => $entry['ability_name'],
                'f3_description' => $entry['f3_description'],
                'f9_description' => $entry['f9_description'],
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
                $this->line("  <fg=cyan>~</> {$entry['hero_name']}");
            } else {
                HeroFurnitureAbility::create($payload);
                $created++;
                $this->line("  <fg=green>+</> {$entry['hero_name']}");
            }
        }

        $this->newLine();
        $this->info("Concluído: {$created} criadas, {$updated} atualizadas, ".count($missing).' sem match.');

        if (! empty($missing) && count($missing) <= 20) {
            $this->newLine();
            $this->comment('Heróis da wiki sem match no DB (até 20):');
            foreach (array_slice($missing, 0, 20) as $name) {
                $this->line('  · '.$name);
            }
        } elseif (count($missing) > 20) {
            $this->newLine();
            $this->comment('Heróis sem match: '.count($missing).' (use --dry-run pra ver todos).');
        }

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
