<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\FactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, EquipmentSeeder::class]);
    }

    public function test_guest_redireciona_para_login(): void
    {
        $this->get('/equipment')->assertRedirect('/login');
    }

    public function test_index_lista_todos_equipamentos_seeded(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/equipment')->assertOk();
        $props = $response->viewData('page')['props'];

        // 14 base items × 5 tiers = 70
        $this->assertCount(70, $props['equipments']);
        $this->assertNotEmpty($props['factions']);
    }

    public function test_filtra_por_faction(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?faction=hypogean')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Lâmina + Bastão = 2 base × 5 tiers = 10 items hypogean
        $this->assertCount(10, $items);
        $this->assertTrue($items->every(fn ($i) => $i['faction_code'] === 'hypogean'));
    }

    public function test_filtra_por_slot_weapon(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?slot=weapon')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Mangual + Lâmina + Bastão = 3 base × 5 tiers = 15
        $this->assertCount(15, $items);
        $this->assertTrue($items->every(fn ($i) => $i['slot'] === 'weapon'));
    }

    public function test_filtra_por_hero_type_intelligence(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?hero_type=intelligence')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Set Místico: 4 base × 5 tiers = 20
        $this->assertCount(20, $items);
        $this->assertTrue($items->every(fn ($i) => $i['hero_type'] === 'intelligence'));
    }

    public function test_filtra_por_quality_tier_t3(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?quality_tier=mythic_t3')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Todos os 14 base items têm versão T3
        $this->assertCount(14, $items);
        $this->assertTrue($items->every(fn ($i) => $i['quality_tier'] === 'mythic_t3'));
    }

    public function test_filtros_combinados(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?set_code=gaius&slot=boots')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // 2 grevas Gaius (lightbearer + celestial) × 5 tiers = 10
        $this->assertCount(10, $items);
    }

    public function test_payload_inclui_stats_e_faction_info(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?set_code=mystic&slot=weapon&quality_tier=mythic_t4')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        $t4 = $items->first();

        $this->assertSame('mystic-bastao-hypogean-t4', $t4['slug']);
        $this->assertSame(37005, $t4['stats']['attack']); // 1500 * 24.67
        $this->assertNotNull($t4['set_bonuses']);
        $this->assertSame(0, $t4['max_upgrade_level']);
    }
}
