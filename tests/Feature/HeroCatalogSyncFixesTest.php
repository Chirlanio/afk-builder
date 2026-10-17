<?php

namespace Tests\Feature;

use App\Models\Hero;
use App\Services\HeroCatalogSync\FandomSyncService;
use App\Services\HeroCatalogSync\FandomWikiClient;
use App\Services\HeroCatalogSync\SyncOptions;
use App\Services\HeroCatalogSync\WikitextHeroParser;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Heróis cuja página da wiki tem o infobox incompleto (sem classe ou tier)
 * eram ignorados pelo sync. FandomSyncService::INFOBOX_FIXES completa só o
 * que veio vazio.
 */
class HeroCatalogSyncFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function service(string $title, string $wikitext): FandomSyncService
    {
        $client = Mockery::mock(FandomWikiClient::class);
        $client->shouldReceive('fetchWikitext')->with($title)->andReturn($wikitext);

        return new FandomSyncService($client, new WikitextHeroParser);
    }

    private function infobox(string $name, string $class, string $rarity, string $faction = 'Lightbearer'): string
    {
        return "{{Hero\n|name={$name}\n|faction={$faction}\n|class={$class}\n|rarity={$rarity}\n|type=Intelligence\n}}\n{$name} is a hero.";
    }

    public function test_classe_em_branco_na_wiki_e_completada(): void
    {
        $result = $this->service('Angelo', $this->infobox('Angelo', '', 'Legendary'))
            ->syncSingleTitle('Angelo', new SyncOptions);

        $this->assertSame(1, $result->created);
        $hero = Hero::with('heroClass', 'tier')->where('slug', 'angelo')->sole();
        $this->assertSame('support', $hero->heroClass->code);
        $this->assertSame('legendary', $hero->tier->code);
        $this->assertTrue($hero->pending_review); // herói novo passa pela aprovação do admin
    }

    public function test_tier_em_branco_na_wiki_e_completado(): void
    {
        $this->service('Bronn', $this->infobox('Bronn', 'Warrior', '', 'Graveborn'))
            ->syncSingleTitle('Bronn', new SyncOptions);

        $this->assertSame('ascended', Hero::with('tier')->where('slug', 'bronn')->sole()->tier->code);
    }

    public function test_o_que_a_wiki_traz_prevalece_sobre_a_correcao(): void
    {
        // Se um dia a wiki preencher a classe do Golus, vale a da wiki.
        $this->service('Golus', $this->infobox('Golus', 'Warrior', 'Legendary', 'Mauler'))
            ->syncSingleTitle('Golus', new SyncOptions);

        $this->assertSame('warrior', Hero::with('heroClass')->where('slug', 'golus')->sole()->heroClass->code);
    }

    public function test_heroi_sem_correcao_continua_ignorado(): void
    {
        $result = $this->service('Gourgue', $this->infobox('Gourgue', '', 'Legendary', 'Draconis'))
            ->syncSingleTitle('Gourgue', new SyncOptions);

        $this->assertSame(0, $result->created);
        $this->assertFalse(Hero::where('slug', 'gourgue')->exists());
    }
}
