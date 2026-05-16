<?php

namespace App\Console\Commands;

use App\Models\Beast;
use App\Services\HeroCatalogSync\FandomWikiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncBeastsCommand extends Command
{
    protected $signature = 'beasts:sync
        {--dry-run : Mostra apenas o que seria criado}';

    protected $description = 'Sincroniza nomes de beasts (pets) da página master da Fandom. Buffs S/I/A precisam ser cadastrados manualmente via admin.';

    private const PAGE_TITLE = 'Beasts';

    public function handle(FandomWikiClient $client): int
    {
        $this->info("Buscando wikitext de '" . self::PAGE_TITLE . "'...");

        $wikitext = $client->fetchWikitext(self::PAGE_TITLE);
        if (! $wikitext) {
            $this->error('Não foi possível obter wikitext da página.');
            return self::FAILURE;
        }

        $names = $this->extractBeastNames($wikitext);
        $total = count($names);

        if ($total === 0) {
            $this->error('Nenhum beast encontrado — verificar parser ou estrutura da wiki.');
            return self::FAILURE;
        }

        $this->info("Encontrados {$total} beasts. Processando...");

        $created = 0;
        $skipped = 0;

        foreach ($names as $name) {
            $slug = Str::slug($name);
            if ($slug === '') {
                $skipped++;
                continue;
            }

            $sourceUrl = 'https://afk-arena.fandom.com/wiki/' . rawurlencode(str_replace(' ', '_', $name));

            if ($this->option('dry-run')) {
                $exists = Beast::where('slug', $slug)->exists();
                $this->line('  [dry] ' . ($exists ? '~' : '+') . " {$slug}");
                continue;
            }

            $existing = Beast::where('slug', $slug)->first();

            if ($existing) {
                // Não sobrescreve buffs cadastrados manualmente — só atualiza name/source_url
                $existing->update([
                    'name' => $name,
                    'source_url' => $sourceUrl,
                ]);
                $skipped++;
                $this->line("  <fg=gray>=</> {$slug} (já existe)");
            } else {
                Beast::create([
                    'slug' => $slug,
                    'name' => $name,
                    'source_url' => $sourceUrl,
                    'buffs_strength' => null,
                    'buffs_intelligence' => null,
                    'buffs_agility' => null,
                    'buffs_team' => null,
                    'is_active' => true,
                ]);
                $created++;
                $this->line("  <fg=green>+</> {$slug}");
            }
        }

        $this->newLine();
        $this->info("Concluído. Criados: {$created}, já existentes: {$skipped}.");
        $this->comment('Lembrete: buffs por scope (Força/Inteligência/Agilidade/Equipe) precisam ser cadastrados manualmente via /admin/beasts.');

        return self::SUCCESS;
    }

    /**
     * Extrai nomes da seção "== Beast List ==". Aceita "* [[Name]]" e "* Name".
     *
     * @return string[]
     */
    private function extractBeastNames(string $wikitext): array
    {
        if (! preg_match('/==\s*Beast\s*List\s*==(.*?)(?=\n==[^=]|\z)/is', $wikitext, $section)) {
            return [];
        }

        $lines = preg_split('/\r?\n/', $section[1]) ?: [];
        $names = [];

        foreach ($lines as $line) {
            // Pega só linhas que começam com "* "
            if (! preg_match('/^\*\s+(.+?)\s*$/u', $line, $m)) {
                continue;
            }

            $content = $m[1];

            // Caso "[[Name]]" ou "[[Page|Label]]" → extrai
            if (preg_match('/^\[\[([^\]|]+?)(?:\|[^\]]+)?\]\]/', $content, $linkMatch)) {
                $name = trim($linkMatch[1]);
            } else {
                // Texto plano — limpa qualquer wikitext residual
                $name = trim(preg_replace("/[\\[\\]']/u", '', $content) ?? $content);
            }

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
