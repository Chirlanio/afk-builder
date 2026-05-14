<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPendingBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
        ]);
    }

    private function makePendingHero(string $slug): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => false,
            'pending_review' => true,
        ]);
    }

    public function test_admin_recebe_pending_count_no_shared_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->makePendingHero('pending-1');
        $this->makePendingHero('pending-2');
        $this->makePendingHero('pending-3');

        $response = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(3, $props['admin']['pending_heroes_count']);
    }

    public function test_admin_sem_pendentes_recebe_zero(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(0, $props['admin']['pending_heroes_count']);
    }

    public function test_nao_admin_recebe_null(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->makePendingHero('pending');

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertNull($props['admin']['pending_heroes_count']);
    }

    public function test_aprovar_heroi_reduz_o_contador(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $pending = $this->makePendingHero('vai-aprovar');

        $r1 = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->assertSame(1, $r1->viewData('page')['props']['admin']['pending_heroes_count']);

        // Simula aprovação via update direto (a flow real usa HeroAdminController@approve)
        $pending->update(['pending_review' => false, 'is_active' => true]);

        $r2 = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->assertSame(0, $r2->viewData('page')['props']['admin']['pending_heroes_count']);
    }

    public function test_guest_nao_recebe_dados_admin(): void
    {
        $this->makePendingHero('orphan');

        // Guest é redirecionado para login antes de qualquer página autenticada,
        // então não há "shared data" pra inspecionar de uma rota auth-protected.
        // Verificamos via rota pública /login que está disponível pra guests:
        $response = $this->get('/login')->assertOk();
        $props = $response->viewData('page')['props'];

        // Admin deve ser null pra guest (sem pending count vazando)
        $this->assertNull($props['admin']['pending_heroes_count'] ?? null);
    }
}
