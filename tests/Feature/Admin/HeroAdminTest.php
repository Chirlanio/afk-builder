<?php

namespace Tests\Feature\Admin;

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

class HeroAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function makePendingHero(string $slug = 'novo-heroi'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => 'Herói Novo',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => false,
            'pending_review' => true,
        ]);
    }

    public function test_nao_admin_recebe_403_ao_acessar_pending_index(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/heroes/pending')->assertForbidden();
    }

    public function test_guest_redireciona_para_login(): void
    {
        $this->get('/admin/heroes/pending')->assertRedirect('/login');
    }

    public function test_admin_lista_heroes_pendentes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makePendingHero();

        $this->actingAs($admin)
            ->get('/admin/heroes/pending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/heroes/pending')
                ->has('heroes', 1)
                ->where('heroes.0.id', $hero->id)
                ->has('factions')
                ->has('heroClasses')
                ->has('tiers')
            );
    }

    public function test_admin_aprova_heroi_pendente(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makePendingHero();

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->id}/approve")
            ->assertRedirect();

        $hero->refresh();
        $this->assertFalse($hero->pending_review);
        $this->assertTrue($hero->is_active);
    }

    public function test_admin_rejeita_heroi_pendente(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makePendingHero();

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->id}/reject")
            ->assertRedirect();

        $hero->refresh();
        $this->assertFalse($hero->pending_review);
        $this->assertFalse($hero->is_active);
    }

    public function test_nao_admin_nao_consegue_aprovar(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $hero = $this->makePendingHero();

        $this->actingAs($user)
            ->patch("/admin/heroes/{$hero->id}/approve")
            ->assertForbidden();

        $hero->refresh();
        $this->assertTrue($hero->pending_review);
    }

    public function test_admin_cria_heroi_manualmente(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $payload = [
            'slug' => 'saveas',
            'name' => 'Saveas',
            'title' => 'Slayer of Souls',
            'faction_id' => Faction::where('code', 'mauler')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'warrior')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
        ];

        $this->actingAs($admin)
            ->post('/admin/heroes', $payload)
            ->assertRedirect();

        $hero = Hero::where('slug', 'saveas')->first();
        $this->assertNotNull($hero);
        $this->assertSame('Saveas', $hero->name);
        $this->assertTrue($hero->is_active);
        $this->assertFalse($hero->pending_review);
    }

    public function test_slug_duplicado_falha_validacao(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->makePendingHero('saveas');

        $this->actingAs($admin)
            ->post('/admin/heroes', [
                'slug' => 'saveas',
                'name' => 'Outro',
                'faction_id' => Faction::where('code', 'mauler')->value('id'),
                'hero_class_id' => HeroClass::where('code', 'warrior')->value('id'),
                'tier_id' => Tier::where('code', 'ascended')->value('id'),
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_slug_invalido_com_maiusculas_falha_validacao(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/admin/heroes', [
                'slug' => 'Saveas',
                'name' => 'Saveas',
                'faction_id' => Faction::where('code', 'mauler')->value('id'),
                'hero_class_id' => HeroClass::where('code', 'warrior')->value('id'),
                'tier_id' => Tier::where('code', 'ascended')->value('id'),
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_campos_obrigatorios_validados(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/admin/heroes', [])
            ->assertSessionHasErrors(['slug', 'name', 'faction_id', 'hero_class_id', 'tier_id']);
    }
}
