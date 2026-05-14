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

class HeroFavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeHero(string $slug = 'test-hero'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_marca_heroi_como_favorito(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->postJson("/heroes/{$hero->id}/favorite")
            ->assertOk()
            ->assertJsonPath('is_favorited', true)
            ->assertJsonPath('favorites_count', 1);

        $this->assertDatabaseHas('user_hero_favorites', [
            'user_id' => $user->id,
            'hero_id' => $hero->id,
        ]);
    }

    public function test_marcar_duas_vezes_e_idempotente(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)->postJson("/heroes/{$hero->id}/favorite")->assertOk();
        $this->actingAs($user)->postJson("/heroes/{$hero->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorites_count', 1);

        $this->assertSame(1, $user->favoriteHeroes()->count());
    }

    public function test_remove_dos_favoritos(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $user->favoriteHeroes()->attach($hero->id);

        $this->actingAs($user)
            ->deleteJson("/heroes/{$hero->id}/favorite")
            ->assertOk()
            ->assertJsonPath('is_favorited', false)
            ->assertJsonPath('favorites_count', 0);

        $this->assertDatabaseMissing('user_hero_favorites', [
            'user_id' => $user->id,
            'hero_id' => $hero->id,
        ]);
    }

    public function test_remover_quando_nao_e_favorito_e_idempotente(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->deleteJson("/heroes/{$hero->id}/favorite")
            ->assertOk()
            ->assertJsonPath('is_favorited', false);
    }

    public function test_favoritos_sao_isolados_por_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($a)->postJson("/heroes/{$hero->id}/favorite")->assertOk();

        $this->assertSame(1, $a->favoriteHeroes()->count());
        $this->assertSame(0, $b->favoriteHeroes()->count());
    }

    public function test_index_inclui_favorite_hero_ids(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');
        $user->favoriteHeroes()->attach($h1->id);

        $response = $this->actingAs($user)->get('/heroes')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame([$h1->id], $props['favoriteHeroIds']);
        $this->assertNotContains($h2->id, $props['favoriteHeroIds']);
    }

    public function test_details_inclui_is_favorited(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('favoriteu');
        $user->favoriteHeroes()->attach($hero->id);

        $response = $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk();

        $this->assertTrue($response->json('isFavorited'));
    }

    public function test_details_is_favorited_false_quando_nao_favoritado(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('nao-fav');

        $response = $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk();

        $this->assertFalse($response->json('isFavorited'));
    }

    public function test_dashboard_inclui_favorites_count(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');
        $user->favoriteHeroes()->attach([$h1->id, $h2->id]);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $stats = $response->viewData('page')['props']['stats'];

        $this->assertSame(2, $stats['favorites_count']);
    }

    public function test_guest_nao_pode_favoritar(): void
    {
        $hero = $this->makeHero();
        $this->postJson("/heroes/{$hero->id}/favorite")->assertStatus(401);
    }

    public function test_deletar_heroi_cascateia_favoritos(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('vai-sumir');
        $user->favoriteHeroes()->attach($hero->id);

        $hero->delete();

        $this->assertDatabaseMissing('user_hero_favorites', [
            'user_id' => $user->id,
            'hero_id' => $hero->id,
        ]);
    }
}
