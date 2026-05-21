<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHeroMythicEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, EquipmentSeeder::class]);
    }

    private function makeHero(string $slug = 'lucius', string $factionCode = 'lightbearer'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $factionCode)->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'type' => 'strength',
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_user_pode_equipar_mythic_equipment(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $boots = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'boots_equipment_id' => $boots->id,
                'boots_equipment_level' => 3,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->where('hero_id', $hero->id)->firstOrFail();
        $this->assertSame($boots->id, $userHero->boots_equipment_id);
        $this->assertSame(3, $userHero->boots_equipment_level);
    }

    public function test_clampa_level_acima_do_max_upgrade_level(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $boots = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();

        // Boots tem max_upgrade_level = 5. Pedir level=10 → validation rejeita (max:5).
        $this->actingAs($user)
            ->postJson('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'boots_equipment_id' => $boots->id,
                'boots_equipment_level' => 10,
            ])
            ->assertStatus(422);
    }

    public function test_t3_equipment_aceita_apenas_level_zero(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        // T3 tem max_upgrade_level=0. Level=0 deve passar, level=1 deve ser clampado pra 0.
        $t3 = Equipment::where('slug', 'mystic-bastao-hypogean-t3')->firstOrFail();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'weapon_equipment_id' => $t3->id,
                'weapon_equipment_level' => 1, // será clampado
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->where('hero_id', $hero->id)->firstOrFail();
        $this->assertSame($t3->id, $userHero->weapon_equipment_id);
        $this->assertSame(0, $userHero->weapon_equipment_level);
    }

    public function test_rejeita_equipment_id_inexistente(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->postJson('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'boots_equipment_id' => 99999, // não existe
                'boots_equipment_level' => 0,
            ])
            ->assertStatus(422);
    }

    public function test_equipment_de_slot_errado_e_descartado(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        // Tenta equipar arma no slot de bota — controller descarta silenciosamente
        $weapon = Equipment::where('slug', 'gaius-mangual-wilder')->firstOrFail();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'boots_equipment_id' => $weapon->id, // arma no slot boots
                'boots_equipment_level' => 3,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->where('hero_id', $hero->id)->firstOrFail();
        // Descartado: slot de boots fica null
        $this->assertNull($userHero->boots_equipment_id);
        $this->assertSame(0, $userHero->boots_equipment_level);
    }

    public function test_pode_equipar_quatro_slots_simultaneamente(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $boots = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();
        $chest = Equipment::where('slug', 'gaius-couraca-wilder')->firstOrFail();
        $helmet = Equipment::where('slug', 'gaius-elmo-graveborn')->firstOrFail();
        $weapon = Equipment::where('slug', 'gaius-mangual-wilder')->firstOrFail();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'boots_equipment_id' => $boots->id,
                'boots_equipment_level' => 5,
                'chest_equipment_id' => $chest->id,
                'chest_equipment_level' => 5,
                'helmet_equipment_id' => $helmet->id,
                'helmet_equipment_level' => 5,
                'weapon_equipment_id' => $weapon->id,
                'weapon_equipment_level' => 5,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->where('hero_id', $hero->id)->firstOrFail();
        $this->assertSame($boots->id, $userHero->boots_equipment_id);
        $this->assertSame($chest->id, $userHero->chest_equipment_id);
        $this->assertSame($helmet->id, $userHero->helmet_equipment_id);
        $this->assertSame($weapon->id, $userHero->weapon_equipment_id);
    }

    public function test_update_remove_equipment_quando_id_e_null(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $boots = Equipment::where('slug', 'gaius-grevas-lightbearer')->firstOrFail();

        $userHero = $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
            'signature_item' => 0,
            'furniture' => 0,
            'engraving' => 0,
            'boots_equipment_id' => $boots->id,
            'boots_equipment_level' => 5,
        ]);

        $this->actingAs($user)
            ->patch("/user/heroes/{$userHero->id}", [
                'level' => 240,
                'ascension_code' => 'ascended_1_star',
                'boots_equipment_id' => null,
                'boots_equipment_level' => 0,
            ])
            ->assertRedirect();

        $userHero->refresh();
        $this->assertNull($userHero->boots_equipment_id);
        $this->assertSame(0, $userHero->boots_equipment_level);
    }

    public function test_hero_details_payload_inclui_available_equipments(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius', 'lightbearer'); // type=strength

        $response = $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('availableEquipments', $data);
        // Hero é Lightbearer + strength → só itens da faction Lightbearer E do tipo strength
        $this->assertNotEmpty($data['availableEquipments']);
        $this->assertTrue(collect($data['availableEquipments'])->every(fn ($e) => $e['hero_type'] === 'strength'));
        $this->assertTrue(collect($data['availableEquipments'])->every(fn ($e) => $e['faction_code'] === 'lightbearer'));
        // Todos devem ter faction_matches=true (filtro garantiu isso)
        $this->assertTrue(collect($data['availableEquipments'])->every(fn ($e) => $e['faction_matches'] === true));
        // Sample: gaius-grevas-lightbearer deve estar lá
        $grevas = collect($data['availableEquipments'])->firstWhere('slug', 'gaius-grevas-lightbearer');
        $this->assertNotNull($grevas);
    }

    public function test_hero_de_faction_diferente_nao_ve_items_de_outras_factions(): void
    {
        $user = User::factory()->create();
        // Hero Mauler + strength → só vê Gaius da faction Mauler
        $hero = Hero::create([
            'slug' => 'brutus',
            'name' => 'Brutus',
            'faction_id' => Faction::where('code', 'mauler')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'type' => 'strength',
            'is_active' => true,
            'pending_review' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk();

        $items = collect($response->json('availableEquipments'));
        $this->assertNotEmpty($items);
        $this->assertTrue($items->every(fn ($e) => $e['faction_code'] === 'mauler'));
        // 4 templates Gaius × 5 tiers = 20 items
        $this->assertCount(20, $items);
    }
}
