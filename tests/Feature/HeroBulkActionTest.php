<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroBulkActionTest extends TestCase
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

    private function makeHero(string $slug): Hero
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

    public function test_guest_recebe_401(): void
    {
        $this->postJson('/heroes/bulk/favorites', ['hero_ids' => [1], 'action' => 'add'])
            ->assertStatus(401);
    }

    public function test_bulk_favorita_em_lote(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');
        $h3 = $this->makeHero('h3');

        $this->actingAs($user)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => [$h1->id, $h2->id, $h3->id],
                'action' => 'add',
            ])
            ->assertOk()
            ->assertJsonPath('action', 'add')
            ->assertJsonPath('affected', 3)
            ->assertJsonPath('favorites_count', 3);

        $this->assertSame(3, $user->favoriteHeroes()->count());
    }

    public function test_bulk_favorita_e_idempotente(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('h');
        $user->favoriteHeroes()->attach($hero->id);

        $this->actingAs($user)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => [$hero->id],
                'action' => 'add',
            ])
            ->assertOk()
            ->assertJsonPath('favorites_count', 1);

        $this->assertSame(1, $user->favoriteHeroes()->count());
    }

    public function test_bulk_desfavorita(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');
        $user->favoriteHeroes()->attach([$h1->id, $h2->id]);

        $this->actingAs($user)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => [$h1->id, $h2->id],
                'action' => 'remove',
            ])
            ->assertOk()
            ->assertJsonPath('favorites_count', 0);
    }

    public function test_bulk_filtra_ids_inexistentes(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('real');

        $this->actingAs($user)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => [$hero->id, 99999, 99998],
                'action' => 'add',
            ])
            ->assertOk()
            ->assertJsonPath('affected', 1);

        $this->assertSame(1, $user->favoriteHeroes()->count());
    }

    public function test_bulk_add_no_roster(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');

        $this->actingAs($user)
            ->postJson('/heroes/bulk/roster', [
                'hero_ids' => [$h1->id, $h2->id],
                'action' => 'add',
            ])
            ->assertOk()
            ->assertJsonPath('action', 'add')
            ->assertJsonPath('affected', 2)
            ->assertJsonPath('roster_count', 2);

        $this->assertSame(2, $user->userHeroes()->count());
        // Verifica defaults
        $first = $user->userHeroes()->first();
        $this->assertSame(1, $first->level);
        $this->assertSame(0, $first->signature_item);
    }

    public function test_bulk_add_no_roster_skipa_ja_existentes(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');

        UserHero::create([
            'user_id' => $user->id,
            'hero_id' => $h1->id,
            'level' => 240,
            'ascension_value' => 16,
            'ascension_code' => 'ascended_5_stars',
            'signature_item' => 40,
            'furniture' => 0,
            'engraving' => 0,
        ]);

        $this->actingAs($user)
            ->postJson('/heroes/bulk/roster', [
                'hero_ids' => [$h1->id, $h2->id],
                'action' => 'add',
            ])
            ->assertOk()
            ->assertJsonPath('affected', 1); // só h2 foi adicionado

        // h1 mantém SI 40 (não foi sobrescrito)
        $this->assertSame(40, $user->userHeroes()->where('hero_id', $h1->id)->first()->signature_item);
        $this->assertSame(2, $user->userHeroes()->count());
    }

    public function test_bulk_remove_do_roster(): void
    {
        $user = User::factory()->create();
        $h1 = $this->makeHero('h1');
        $h2 = $this->makeHero('h2');
        UserHero::create([
            'user_id' => $user->id, 'hero_id' => $h1->id,
            'level' => 1, 'ascension_value' => 0, 'ascension_code' => null,
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);
        UserHero::create([
            'user_id' => $user->id, 'hero_id' => $h2->id,
            'level' => 1, 'ascension_value' => 0, 'ascension_code' => null,
            'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $this->actingAs($user)
            ->postJson('/heroes/bulk/roster', [
                'hero_ids' => [$h1->id, $h2->id],
                'action' => 'remove',
            ])
            ->assertOk()
            ->assertJsonPath('affected', 2)
            ->assertJsonPath('roster_count', 0);
    }

    public function test_rejeita_action_invalida(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('h');

        $this->actingAs($user)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => [$hero->id],
                'action' => 'invalid',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    public function test_rejeita_hero_ids_vazio(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => [],
                'action' => 'add',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hero_ids']);
    }

    public function test_rejeita_acima_de_200_ids(): void
    {
        $user = User::factory()->create();

        $ids = range(1, 201);

        $this->actingAs($user)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => $ids,
                'action' => 'add',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hero_ids']);
    }

    public function test_isola_por_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $hero = $this->makeHero('h');

        $this->actingAs($userA)
            ->postJson('/heroes/bulk/favorites', [
                'hero_ids' => [$hero->id],
                'action' => 'add',
            ])
            ->assertOk();

        $this->assertSame(1, $userA->favoriteHeroes()->count());
        $this->assertSame(0, $userB->favoriteHeroes()->count());
    }

    public function test_dedupe_ids_no_payload(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('h');

        $this->actingAs($user)
            ->postJson('/heroes/bulk/roster', [
                'hero_ids' => [$hero->id, $hero->id, $hero->id],
                'action' => 'add',
            ])
            ->assertOk()
            ->assertJsonPath('affected', 1);
    }
}
