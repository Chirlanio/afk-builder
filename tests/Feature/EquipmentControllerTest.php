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

        // 12 templates × 8 factions × 5 tiers = 480
        $this->assertCount(480, $props['equipments']);
        $this->assertNotEmpty($props['factions']);
    }

    public function test_filtra_por_faction(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?faction=hypogean')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // 12 templates × 5 tiers por faction = 60
        $this->assertCount(60, $items);
        $this->assertTrue($items->every(fn ($i) => $i['faction_code'] === 'hypogean'));
    }

    public function test_filtra_por_slot_weapon(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?slot=weapon')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // 3 weapons (Mangual+Lâmina+Bastão) × 8 factions × 5 tiers = 120
        $this->assertCount(120, $items);
        $this->assertTrue($items->every(fn ($i) => $i['slot'] === 'weapon'));
    }

    public function test_filtra_por_hero_type_intelligence(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?hero_type=intelligence')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // Set Místico: 4 templates × 8 factions × 5 tiers = 160
        $this->assertCount(160, $items);
        $this->assertTrue($items->every(fn ($i) => $i['hero_type'] === 'intelligence'));
    }

    public function test_filtra_por_quality_tier_t3(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?quality_tier=mythic_t3')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // 12 templates × 8 factions = 96
        $this->assertCount(96, $items);
        $this->assertTrue($items->every(fn ($i) => $i['quality_tier'] === 'mythic_t3'));
    }

    public function test_filtros_combinados(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?set_code=gaius&slot=boots&faction=lightbearer')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        // 1 template (gaius-grevas) × 1 faction × 5 tiers = 5
        $this->assertCount(5, $items);
    }

    public function test_payload_inclui_stats_e_faction_info(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->get('/equipment?set_code=mystic&slot=weapon&quality_tier=mythic_t4&faction=hypogean')
            ->assertOk();

        $items = collect($response->viewData('page')['props']['equipments']);
        $t4 = $items->first();

        $this->assertSame('mystic-bastao-hypogean-t4', $t4['slug']);
        $this->assertSame(37005, $t4['stats']['attack']); // 1500 * 24.67
        $this->assertNotNull($t4['set_bonuses']);
        $this->assertSame(0, $t4['max_upgrade_level']);
    }
}
