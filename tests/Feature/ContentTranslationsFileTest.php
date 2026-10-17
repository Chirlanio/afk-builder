<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentTranslationsFileTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
        $this->dir = sys_get_temp_dir().'/afk-translations-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    private function hero(): Hero
    {
        $hero = Hero::create([
            'slug' => 'lucius', 'name' => 'Lucius', 'lore' => 'Lucius is a paladin.',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true, 'pending_review' => false,
        ]);
        $hero->abilities()->create(['name' => 'Divine Strike', 'type' => 'active', 'display_order' => 0, 'description' => 'Lucius smashes the ground.']);

        return $hero;
    }

    public function test_exporta_traduz_e_importa(): void
    {
        $hero = $this->hero();
        $file = $this->dir.'/textos.jsonl';

        $this->artisan('content:translations', ['action' => 'export', 'file' => $file])->assertSuccessful();
        $rows = array_map(fn ($l) => json_decode($l, true), file($file, FILE_IGNORE_NEW_LINES));
        $this->assertCount(3, $rows); // lore + nome + descrição da habilidade

        Http::fake(['translate.googleapis.com/*' => fn ($request) => Http::response([[['[pt] '.$request['q'], $request['q']]]])]);
        $this->artisan('content:translations', ['action' => 'translate', 'file' => $file])->assertSuccessful();

        $this->artisan('content:translations', ['action' => 'import', 'file' => $this->dir.'/textos.pt.jsonl'])->assertSuccessful();

        $this->assertSame('[pt] Lucius is a paladin.', $hero->fresh()->lore_pt);
        $ability = $hero->abilities()->first();
        $this->assertSame('[pt] Divine Strike', $ability->name_pt);
        $this->assertSame('[pt] Lucius smashes the ground.', $ability->description_pt);

        // Nada mais a exportar depois de importar.
        $this->artisan('content:translations', ['action' => 'export', 'file' => $file])->assertSuccessful();
        $this->assertSame('', trim(file_get_contents($file)));
    }

    public function test_traducao_retoma_de_onde_parou(): void
    {
        $this->hero();
        $file = $this->dir.'/textos.jsonl';
        $this->artisan('content:translations', ['action' => 'export', 'file' => $file]);

        Http::fake(['translate.googleapis.com/*' => Http::response([[['ok', 'x']]])]);
        $this->artisan('content:translations', ['action' => 'translate', 'file' => $file]);
        $this->artisan('content:translations', ['action' => 'translate', 'file' => $file]);

        Http::assertSentCount(3); // a segunda rodada não refaz nada
        $this->assertCount(3, file($this->dir.'/textos.pt.jsonl', FILE_IGNORE_NEW_LINES));
    }

    public function test_import_nao_sobrescreve_e_ignora_tipos_desconhecidos(): void
    {
        $hero = $this->hero();
        $hero->update(['lore_pt' => 'Tradução revisada à mão']);
        $file = $this->dir.'/in.pt.jsonl';
        file_put_contents($file, implode(PHP_EOL, [
            json_encode(['type' => 'hero', 'id' => $hero->id, 'field' => 'lore', 'translation' => 'Outra']),
            json_encode(['type' => 'users', 'id' => 1, 'field' => 'password', 'translation' => 'x']),
        ]));

        $this->artisan('content:translations', ['action' => 'import', 'file' => $file])
            ->expectsOutputToContain('0 tradução(ões) gravada(s); 2 ignorada(s)')
            ->assertSuccessful();

        $this->assertSame('Tradução revisada à mão', $hero->fresh()->lore_pt);
    }
}
