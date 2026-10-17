<?php

namespace Database\Seeders;

use App\Models\Guide;
use App\Services\HeroCatalogSync\FandomWikiClient;
use App\Services\HeroCatalogSync\WikitextHeroParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Throwable;

class GuideSeeder extends Seeder
{
    /**
     * Mapeamento provisório de termos do jogo BR para títulos de páginas
     * da Fandom Wiki (em inglês). Refinar conforme necessário.
     */
    private const ENTRIES = [
        [
            'slug' => 'cristal-ressonante',
            'title' => 'Cristal Ressonante (Resonating Crystal)',
            'category' => Guide::CATEGORY_MECANICAS,
            'wiki_page' => 'Resonating Crystal',
            'static_content' => 'cristal-ressonante.md',
            'summary' => 'Pentagrama em Ranhorn que iguala heróis selecionados ao nível do seu 5º melhor — depois de 5 heróis no nível 240, vira cristal upgradeável.',
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
            'title' => 'Atributos (Stats) do Herói',
            'category' => Guide::CATEGORY_MECANICAS,
            'wiki_page' => 'Attributes',
            'static_content' => 'atributos.md',
            'summary' => 'Glossário completo dos atributos PT-BR: vida, ataque, defesa, crítico (ADC/RDC), físico (PF/RF), mágico (PM/RM/EM/SM), controle (PER/TC), cura, pressa e dracônico — com fórmulas e abreviações oficiais.',
            'display_order' => 80,
        ],
    ];

    private const CONTENT_MAX_CHARS = 6000;

    public function run(): void
    {
        $client = app(FandomWikiClient::class);
        $parser = app(WikitextHeroParser::class);

        // Remove o slug antigo `critico-ressonante` (typo da seedagem original — substituído por `cristal-ressonante`)
        Guide::where('slug', 'critico-ressonante')->delete();

        foreach (self::ENTRIES as $entry) {
            $content = null;
            $sourceUrl = null;

            // 1) Se a entry tem `static_content`, usa o markdown do disco e ignora fetch da wiki
            if (! empty($entry['static_content'])) {
                $path = __DIR__.'/guides/'.$entry['static_content'];
                if (File::exists($path)) {
                    $content = File::get($path);
                    $sourceUrl = 'https://afk-arena.fandom.com/wiki/'.rawurlencode(str_replace(' ', '_', $entry['wiki_page']));
                } else {
                    $this->command?->warn("static_content '{$entry['static_content']}' não encontrado em guides/.");
                }
            }

            // 2) Senão, busca da Fandom Wiki (comportamento original)
            if ($content === null && ! empty($entry['wiki_page'])) {
                try {
                    $wikitext = $client->fetchWikitext($entry['wiki_page']);
                    if ($wikitext) {
                        $content = $parser->wikitextToMarkdown($wikitext);
                        if (mb_strlen($content) > self::CONTENT_MAX_CHARS) {
                            $content = mb_substr($content, 0, self::CONTENT_MAX_CHARS)."\n\n*[…] (Conteúdo truncado — veja a fonte para versão completa)*";
                        }
                        $sourceUrl = 'https://afk-arena.fandom.com/wiki/'.rawurlencode(str_replace(' ', '_', $entry['wiki_page']));
                    }
                } catch (Throwable $e) {
                    $this->command?->warn("Falha ao buscar '{$entry['wiki_page']}': {$e->getMessage()}");
                }
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
