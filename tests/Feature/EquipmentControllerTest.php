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

        $this->assertCount(18, $props['equipments']);
        $this->assertNotEmpty($props['factions']);
    }

    public function test_filtra_por_faction(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?faction=hypogean')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Lâmina + 5 tiers do Bastão = 6 items hypogean
        $this->assertCount(6, $items);
        $this->assertTrue($items->every(fn ($i) => $i['faction_code'] === 'hypogean'));
    }

    public function test_filtra_por_slot_weapon(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?slot=weapon')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Mangual (Gaius) + Lâmina (Ancelot) + Bastão × 5 tiers (Místico) = 7
        $this->assertCount(7, $items);
        $this->assertTrue($items->every(fn ($i) => $i['slot'] === 'weapon'));
    }

    public function test_filtra_por_hero_type_intelligence(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?hero_type=intelligence')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Set Místico inteiro: 3 armaduras + 5 tiers do Bastão = 8
        $this->assertCount(8, $items);
        $this->assertTrue($items->every(fn ($i) => $i['hero_type'] === 'intelligence'));
    }

    public function test_filtra_por_quality_tier_t3(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?quality_tier=mythic_t3')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        $this->assertCount(1, $items);
        $this->assertSame('mystic-bastao-hypogean-t3', $items[0]['slug']);
    }

    public function test_filtros_combinados(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?set_code=gaius&slot=boots')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // 2 grevas Gaius (lightbearer + celestial)
        $this->assertCount(2, $items);
    }

    public function test_payload_inclui_stats_e_faction_info(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?slug-filter=ignored&slot=weapon&quality_tier=mythic_t4')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        $t4 = $items->first();

        $this->assertSame('mystic-bastao-hypogean-t4', $t4['slug']);
        $this->assertSame(37000, $t4['stats']['attack']);
        $this->assertNotNull($t4['set_bonuses']);
        $this->assertSame(0, $t4['max_upgrade_level']);
    }
}
