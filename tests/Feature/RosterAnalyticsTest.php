<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redireciona_para_login(): void
    {
        $this->get('/roster/analytics')->assertRedirect('/login');
    }

    public function test_roster_vazio_renderiza_componente_com_size_zero(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/roster/analytics')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(0, $props['rosterSize']);
        $this->assertSame(0, $props['summary']['total_power']);
        $this->assertCount(0, $props['top10']);
        // Mesmo com roster vazio, byFaction/byClass/byTier vêm com buckets zerados (catálogo seedado)
        $this->assertCount(8, $props['byFaction']);
        $this->assertCount(5, $props['byClass']);
        $this->assertCount(3, $props['byTier']);
        // Gaps reportam todas as facções e classes (todas com 0 invested)
        $this->assertNotEmpty($props['gaps']);
    }

    public function test_payload_calcula_summary_e_distribuicoes(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $h1 = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $h2 = $this->makeHero('beta', 'lightbearer', 'mage', 'ascended');
        $h3 = $this->makeHero('gamma', 'mauler', 'warrior', 'legendary');

        $user->defaultGameAccount->userHeroes()->createMany([
            ['hero_id' => $h1->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 30, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $h2->id, 'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars', 'signature_item' => 40, 'furniture' => 9, 'engraving' => 80],
            ['hero_id' => $h3->id, 'level' => 160, 'ascension_value' => 9, 'ascension_code' => 'mythic_plus', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
        ]);

        $response = $this->actingAs($user)->get('/roster/analytics')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(3, $props['rosterSize']);
        $summary = $props['summary'];
        // 2 ascended_value >= 10 + 1 com SI 30 (h1) + 1 com F9 (h2 também) → invested_count = 2 (h1 e h2)
        $this->assertSame(2, $summary['invested_count']);
        $this->assertGreaterThan(0, $summary['total_power']);
        $this->assertGreaterThan(0, $summary['max_power']);
        // Médias devem ser numbers floats
        $this->assertIsNumeric($summary['avg_signature_item']);

        // byFaction: 8 buckets (todas as facções do seeder), mas só lightbearer (2) e mauler (1) com count > 0
        $factions = collect($props['byFaction']);
        $lb = $factions->firstWhere('code', 'lightbearer');
        $this->assertSame(2, $lb['count']);
        $this->assertSame(2, $lb['invested_count']);

        $maul = $factions->firstWhere('code', 'mauler');
        $this->assertSame(1, $maul['count']);
        $this->assertSame(0, $maul['invested_count']);

        // byClass: 5 buckets, tank/mage/warrior com count = 1
        $classes = collect($props['byClass']);
        $tank = $classes->firstWhere('code', 'tank');
        $this->assertSame(1, $tank['count']);

        // byTier: 3 buckets, ascended (2) + legendary (1)
        $tiers = collect($props['byTier']);
        $asc = $tiers->firstWhere('code', 'ascended');
        $this->assertSame(2, $asc['count']);
        $leg = $tiers->firstWhere('code', 'legendary');
        $this->assertSame(1, $leg['count']);
    }

    public function test_top10_ordenado_por_power_desc(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // Heroes com investimento diferente → power diferente
        $hero_strong = $this->makeHero('strong', 'lightbearer', 'tank', 'ascended');
        $hero_weak = $this->makeHero('weak', 'wilder', 'support', 'legendary');

        $user->defaultGameAccount->userHeroes()->createMany([
            ['hero_id' => $hero_weak->id, 'level' => 100, 'ascension_value' => 4, 'ascension_code' => 'elite', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $hero_strong->id, 'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars', 'signature_item' => 40, 'furniture' => 9, 'engraving' => 80],
        ]);

        $response = $this->actingAs($user)->get('/roster/analytics')->assertOk();
        $top = $response->viewData('page')['props']['top10'];

        $this->assertCount(2, $top);
        $this->assertSame('strong', $top[0]['slug']);
        $this->assertSame('weak', $top[1]['slug']);
        $this->assertGreaterThan($top[1]['power'], $top[0]['power']);
    }

    public function test_payload_inclui_pentagrama_do_cristal(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // 7 heros com niveis variados — top 5 vai ser o pentagrama
        $levels = [240, 220, 200, 180, 160, 100, 50];
        foreach ($levels as $i => $level) {
            $h = $this->makeHero("h{$i}", 'lightbearer', 'tank', 'ascended');
            $user->defaultGameAccount->userHeroes()->create([
                'hero_id' => $h->id, 'level' => $level, 'ascension_value' => 12,
                'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
            ]);
        }

        $response = $this->actingAs($user)->get('/roster/analytics')->assertOk();
        $pentagram = $response->viewData('page')['props']['pentagram'];

        $this->assertCount(5, $pentagram['heroes']);
        $this->assertSame([240, 220, 200, 180, 160], collect($pentagram['heroes'])->pluck('level')->all());
        $this->assertSame(160, $pentagram['shared_level']);
        $this->assertTrue($pentagram['is_full']);
        $this->assertFalse($pentagram['is_at_240']); // 5º maior = 160 < 240
        $this->assertSame(275, $pentagram['level_cap']); // 240 + 7*5 (7 ascendidos não-dim)
    }

    public function test_gaps_reporta_faccoes_sub_investidas(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // Só 1 hero investido → 7 facções sem nenhum + 4 classes faltando + lightbearer com 1/3
        $h = $this->makeHero('solo', 'lightbearer', 'tank', 'ascended');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $h->id, 'level' => 240, 'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $response = $this->actingAs($user)->get('/roster/analytics')->assertOk();
        $gaps = collect($response->viewData('page')['props']['gaps']);

        // 8 facções no total → 7 com 0 invested + lightbearer com 1 invested (< 3) = 8 gaps de facção
        $factionGaps = $gaps->where('type', 'faction_underinvested');
        $this->assertCount(8, $factionGaps);

        // Classes: tank tem 1 → não falta. As outras 4 estão com count=0 → 4 gaps
        $classMissingGaps = $gaps->where('type', 'class_missing');
        $this->assertCount(4, $classMissingGaps);
    }

    private function seedCatalog(): void
    {
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeHero(string $slug, string $factionCode, string $classCode, string $tierCode): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $factionCode)->value('id'),
            'hero_class_id' => HeroClass::where('code', $classCode)->value('id'),
            'tier_id' => Tier::where('code', $tierCode)->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }
}
