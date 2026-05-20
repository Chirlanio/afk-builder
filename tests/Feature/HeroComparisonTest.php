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

class HeroComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redireciona_para_login(): void
    {
        $this->get('/roster/compare')->assertRedirect('/login');
    }

    public function test_renderiza_pagina_vazia_quando_sem_heroes_param(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/roster/compare')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame([], $props['heroes']);
        $this->assertSame([], $props['rosterOptions']);
        $this->assertSame(2, $props['minHeroes']);
        $this->assertSame(4, $props['maxHeroes']);
    }

    public function test_lista_roster_options_para_picker(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $h1 = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $h2 = $this->makeHero('beta', 'mauler', 'mage', 'legendary');
        $user->userHeroes()->createMany([
            ['hero_id' => $h1->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 30, 'furniture' => 3, 'engraving' => 30],
            ['hero_id' => $h2->id, 'level' => 160, 'ascension_value' => 9, 'ascension_code' => 'mythic_plus', 'signature_item' => 10, 'furniture' => 0, 'engraving' => 0],
        ]);

        $response = $this->actingAs($user)->get('/roster/compare')->assertOk();
        $options = collect($response->viewData('page')['props']['rosterOptions']);

        $this->assertCount(2, $options);
        $this->assertTrue($options->contains('slug', 'alpha'));
        $this->assertTrue($options->contains('slug', 'beta'));
    }

    public function test_compara_dois_heros_com_payload_completo(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $h1 = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $h2 = $this->makeHero('beta', 'mauler', 'mage', 'legendary');
        $user->userHeroes()->createMany([
            ['hero_id' => $h1->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 30, 'furniture' => 3, 'engraving' => 30,
             'weapon_tier' => 'T5', 'weapon_level' => 20, 'helmet_tier' => 'T5', 'helmet_level' => 20, 'chest_tier' => 'T5', 'chest_level' => 20, 'boots_tier' => 'T5', 'boots_level' => 20],
            ['hero_id' => $h2->id, 'level' => 160, 'ascension_value' => 9, 'ascension_code' => 'mythic_plus', 'signature_item' => 10, 'furniture' => 0, 'engraving' => 0],
        ]);

        $response = $this->actingAs($user)
            ->get('/roster/compare?heroes[]=alpha&heroes[]=beta')
            ->assertOk();

        $heroes = $response->viewData('page')['props']['heroes'];

        $this->assertCount(2, $heroes);
        $this->assertSame('alpha', $heroes[0]['slug']);
        $this->assertSame('beta', $heroes[1]['slug']);

        // Alpha deve ter power maior (mais investido)
        $this->assertGreaterThan($heroes[1]['power']['total'], $heroes[0]['power']['total']);

        // Alpha tem equipamento; beta não
        $this->assertSame('T5', $heroes[0]['equipment']['weapon_tier']);
        $this->assertNull($heroes[1]['equipment']['weapon_tier']);

        // Ambos têm upgrades sugeridos
        $this->assertNotEmpty($heroes[0]['upgrades']);
        $this->assertNotEmpty($heroes[1]['upgrades']);

        // Cada upgrade tem o shape esperado
        $u = $heroes[0]['upgrades'][0];
        $this->assertArrayHasKey('kind', $u);
        $this->assertArrayHasKey('from', $u);
        $this->assertArrayHasKey('to', $u);
        $this->assertArrayHasKey('power_gain', $u);
    }

    public function test_preserva_ordem_dos_slugs_na_query(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $a = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $b = $this->makeHero('beta', 'mauler', 'mage', 'legendary');
        $c = $this->makeHero('gamma', 'wilder', 'support', 'legendary');
        $user->userHeroes()->createMany([
            ['hero_id' => $a->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $b->id, 'level' => 160, 'ascension_value' => 9, 'ascension_code' => 'mythic_plus', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $c->id, 'level' => 160, 'ascension_value' => 9, 'ascension_code' => 'mythic_plus', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
        ]);

        // Solicita na ordem gamma, alpha, beta — backend deve preservar
        $response = $this->actingAs($user)
            ->get('/roster/compare?heroes[]=gamma&heroes[]=alpha&heroes[]=beta')
            ->assertOk();

        $slugs = collect($response->viewData('page')['props']['heroes'])->pluck('slug')->all();
        $this->assertSame(['gamma', 'alpha', 'beta'], $slugs);
    }

    public function test_hero_que_nao_pertence_ao_user_e_ignorado(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $h1 = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $h2 = $this->makeHero('outsider', 'mauler', 'mage', 'ascended');

        // Só user tem alpha; only other tem outsider
        $user->userHeroes()->create(['hero_id' => $h1->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0]);
        $other->userHeroes()->create(['hero_id' => $h2->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0]);

        $response = $this->actingAs($user)
            ->get('/roster/compare?heroes[]=alpha&heroes[]=outsider')
            ->assertOk();

        $heroes = $response->viewData('page')['props']['heroes'];
        $this->assertCount(1, $heroes);
        $this->assertSame('alpha', $heroes[0]['slug']);
    }

    public function test_max_heroes_e_respeitado_via_validacao(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // 5 slugs > maxHeroes=4 → 422
        $this->actingAs($user)
            ->get('/roster/compare?heroes[]=a&heroes[]=b&heroes[]=c&heroes[]=d&heroes[]=e')
            ->assertStatus(302); // Laravel redireciona com erros pra páginas Inertia GET
    }

    public function test_slugs_duplicados_sao_deduplicados(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $h = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $user->userHeroes()->create(['hero_id' => $h->id, 'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 30, 'furniture' => 0, 'engraving' => 0]);

        $response = $this->actingAs($user)
            ->get('/roster/compare?heroes[]=alpha&heroes[]=alpha&heroes[]=alpha')
            ->assertOk();

        $heroes = $response->viewData('page')['props']['heroes'];
        $this->assertCount(1, $heroes);
    }

    public function test_power_breakdown_inclui_componentes(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $h = $this->makeHero('alpha', 'lightbearer', 'tank', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $h->id,
            'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars',
            'signature_item' => 30, 'furniture' => 9, 'engraving' => 80,
            'weapon_tier' => 'T6', 'weapon_level' => 20,
            'helmet_tier' => 'T6', 'helmet_level' => 20,
            'chest_tier' => 'T6', 'chest_level' => 20,
            'boots_tier' => 'T6', 'boots_level' => 20,
        ]);

        $response = $this->actingAs($user)
            ->get('/roster/compare?heroes[]=alpha')
            ->assertOk();

        // Só 1 hero retorna; o frontend mostra mensagem pedindo mais — mas o payload existe
        $heroes = $response->viewData('page')['props']['heroes'];
        $this->assertCount(1, $heroes);

        $power = $heroes[0]['power'];
        $this->assertArrayHasKey('total', $power);
        $this->assertArrayHasKey('stat_contribution', $power);
        $this->assertArrayHasKey('equipment_contribution', $power);
        $this->assertArrayHasKey('artifact_contribution', $power);
        $this->assertArrayHasKey('collection_contribution', $power);

        // T6 Lv20 × 4 slots = 400 contribution
        $this->assertSame(400, $power['equipment_contribution']);
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
