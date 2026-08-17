<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroFeature;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertOk();
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

    private function makeHero(string $slug, string $factionCode = 'lightbearer', string $classCode = 'tank', string $tierCode = 'ascended'): Hero
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

    public function test_payload_inclui_estatisticas_novas_e_milestones(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // 4 heroes diversos no roster: 1 com SI 30, 1 com SI 40 (Celestial), 1 com F9, 1 cru
        $h1 = $this->makeHero('h-si30', 'lightbearer', 'tank', 'ascended');
        $h2 = $this->makeHero('h-si40', 'celestial', 'mage', 'ascended');
        $h3 = $this->makeHero('h-f9', 'wilder', 'ranger', 'ascended');
        $h4 = $this->makeHero('h-baseline', 'graveborn', 'warrior', 'legendary');

        $user->defaultGameAccount->userHeroes()->createMany([
            ['hero_id' => $h1->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 30, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $h2->id, 'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars', 'signature_item' => 40, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $h3->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 9, 'engraving' => 0],
            ['hero_id' => $h4->id, 'level' => 160, 'ascension_value' => 9, 'ascension_code' => 'mythic_plus', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 85],
        ]);

        HeroFeature::create(['hero_id' => $h1->id, 'tag' => HeroFeature::TAG_PVP_TOP, 'source' => 'afk.guide']);
        HeroFeature::create(['hero_id' => $h3->id, 'tag' => HeroFeature::TAG_TWISTED_REALM_TOP, 'source' => 'afk.guide']);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();

        $props = $response->viewData('page')['props'];
        $stats = $props['stats'];

        $this->assertSame(4, $stats['roster_total']);
        $this->assertSame(2, $stats['roster_si_30']);
        $this->assertSame(1, $stats['roster_si_40']);
        $this->assertSame(1, $stats['roster_f9']);
        $this->assertSame(1, $stats['roster_eng_80']);

        // Distribuição: 4 facções diferentes → 4 entries
        $this->assertCount(4, $props['rosterByFaction']);
        // 4 classes diferentes
        $this->assertCount(4, $props['rosterByClass']);
        // 2 tiers (ascended + legendary)
        $this->assertCount(2, $props['rosterByTier']);

        // Top power: 4 heroes, todos retornados (limit 5)
        $this->assertCount(4, $props['topPowerHeroes']);

        // Featured do roster
        $featured = $props['featuredInRoster'];
        $this->assertCount(1, $featured['pvp_top']);
        $this->assertSame('h-si30', $featured['pvp_top'][0]['slug']);
        $this->assertCount(1, $featured['twisted_realm_top']);
        $this->assertSame('h-f9', $featured['twisted_realm_top'][0]['slug']);
    }

    public function test_roster_vazio_retorna_distribuicoes_vazias(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(0, $props['stats']['roster_total']);
        $this->assertSame(0, $props['stats']['roster_si_30']);
        $this->assertCount(0, $props['rosterByFaction']);
        $this->assertCount(0, $props['topPowerHeroes']);
        $this->assertSame([], $props['featuredInRoster']['pvp_top']);
        $this->assertSame([], $props['featuredInRoster']['twisted_realm_top']);
    }
}
