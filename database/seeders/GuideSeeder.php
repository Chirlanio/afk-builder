<?php

namespace Database\Seeders;

use App\Models\Guide;
use App\Services\HeroCatalogSync\FandomWikiClient;
use App\Services\HeroCatalogSync\WikitextHeroParser;
use Illuminate\Database\Seeder;
use Throwable;

class GuideSeeder extends Seeder
{
    /**
     * Mapeamento provisório de termos do jogo BR para títulos de páginas
     * da Fandom Wiki (em inglês). Refinar conforme necessário.
     */
    private const ENTRIES = [
        [
            'slug' => 'critico-ressonante',
            'title' => 'Crítico Ressonante (Resonating Crystal)',
            'category' => Guide::CATEGORY_MECANICAS,
            'wiki_page' => 'Resonating Crystal',
            'summary' => 'Cristal pentagrama em Ranhorn que eleva o nível de heróis selecionados ao mesmo dos seus 5 melhores.',
            'display_order' => 10,
        ],
        [
            'slug' => 'chifre-de-dragao',
            'title' => 'Chifre de Dragão (Dragon\'s Rest)',
            'category' => Guide::CATEGORY_MODOS,
            'wiki_page' => 'Dragon\'s Rest',
            'summary' => 'Capítulo 36 da Campanha — sede dos testes envolvendo Força Dracônica.',
            'display_order' => 20,
        ],
        [
            'slug' => 'terreno-das-bestas',
            'title' => 'Terreno das Bestas (Beast Grounds)',
            'category' => Guide::CATEGORY_MECANICAS,
            'wiki_page' => 'Beast Grounds',
            'summary' => 'Pets de batalha que aprimoram atributos e oferecem habilidades únicas durante combate.',
            'display_order' => 30,
        ],
        [
            'slug' => 'galeria-tenebrosa',
            'title' => 'Galeria Tenebrosa (Ghoulish Gallery)',
            'category' => Guide::CATEGORY_MODOS,
            'wiki_page' => 'Ghoulish Gallery',
            'summary' => 'Sistema de Coleções (artefatos alternativos) explorado semanalmente. Substitui artefatos originais em vários modos.',
            'display_order' => 40,
        ],
        [
            'slug' => 'fenda-temporal',
            'title' => 'Fenda Temporal (Temporal Rift)',
            'category' => Guide::CATEGORY_MODOS,
            'wiki_page' => 'Temporal Rift',
            'summary' => 'Modo de andares infinitos com batalhas e recompensas progressivas — Hall of Fame, Fountain of Time, Sanctioned Realm.',
            'display_order' => 50,
        ],
        [
            'slug' => 'hotel-oak',
            'title' => 'Hotel Oak (The Oak Inn)',
            'category' => Guide::CATEGORY_MECANICAS,
            'wiki_page' => 'The Oak Inn',
            'summary' => 'Sistema de hotel onde heróis Ascendidos ganham bônus de móveis. Desbloqueia ao zerar o Capítulo 4 (visitar) ou Capítulo 17 (próprio).',
            'display_order' => 60,
        ],
        [
            'slug' => 'moveis-oak-inn',
            'title' => 'Móveis do Hotel (Oak Inn Furniture)',
            'category' => Guide::CATEGORY_MECANICAS,
            'wiki_page' => 'Oak Inn Furniture',
            'summary' => '16 conjuntos × 9 peças, 4 raridades (Comum/Elite/Lendário/Mítico). Móveis Míticos exclusivos por herói desbloqueiam habilidades.',
            'display_order' => 70,
        ],
        [
            'slug' => 'atributos',
            'title' => 'Atributos e bônus de formação',
            'category' => Guide::CATEGORY_MECANICAS,
            'wiki_page' => 'Attributes',
            'summary' => 'Stats que os heróis possuem (ATK/HP/Defesa/Crit/etc.), como aumentá-los e os bônus que formações ativam (3+2, 4-stack, 5-stack).',
            'display_order' => 80,
        ],
    ];

    private const CONTENT_MAX_CHARS = 6000;

    public function run(): void
    {
        $client = app(FandomWikiClient::class);
        $parser = app(WikitextHeroParser::class);

        foreach (self::ENTRIES as $entry) {
            $content = null;
            $sourceUrl = null;

            try {
                $wikitext = $client->fetchWikitext($entry['wiki_page']);
                if ($wikitext) {
                    $content = $parser->wikitextToMarkdown($wikitext);
                    if (mb_strlen($content) > self::CONTENT_MAX_CHARS) {
                        $content = mb_substr($content, 0, self::CONTENT_MAX_CHARS) . "\n\n*[…] (Conteúdo truncado — veja a fonte para versão completa)*";
                    }
                    $sourceUrl = 'https://afk-arena.fandom.com/wiki/' . rawurlencode(str_replace(' ', '_', $entry['wiki_page']));
                }
            } catch (Throwable $e) {
                $this->command?->warn("Falha ao buscar '{$entry['wiki_page']}': {$e->getMessage()}");
            }

            Guide::updateOrCreate(
                ['slug' => $entry['slug']],
                [
                    'title' => $entry['title'],
                    'category' => $entry['category'],
                    'summary' => $entry['summary'],
                    'content' => $content,
                    'source_url' => $sourceUrl,
                    'display_order' => $entry['display_order'],
                ],
            );
        }
    }
}
