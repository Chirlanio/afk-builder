<?php

namespace Tests\Feature\Admin;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroStat;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroStatsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function makeHero(string $slug = 'lucius'): Hero
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

    public function test_guest_redireciona_para_login(): void
    {
        $hero = $this->makeHero();
        $this->get("/admin/heroes/{$hero->slug}/stats")->assertRedirect('/login');
    }

    public function test_nao_admin_recebe_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $hero = $this->makeHero();

        $this->actingAs($user)->get("/admin/heroes/{$hero->slug}/stats")->assertForbidden();
        $this->actingAs($user)->patch("/admin/heroes/{$hero->slug}/stats", ['hp' => 100])->assertForbidden();
    }

    public function test_admin_acessa_form_de_edicao(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero();

        $this->actingAs($admin)
            ->get("/admin/heroes/{$hero->slug}/stats")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/heroes/stats')
                ->where('hero.slug', $hero->slug)
                ->where('stats', null)
            );
    }

    public function test_admin_cria_stats_com_sucesso(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero();

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->slug}/stats", [
                'hp' => 1500,
                'attack' => 320,
                'defense' => 280,
                'crit_rate' => 50,
            ])
            ->assertRedirect("/heroes/{$hero->slug}");

        $this->assertDatabaseHas('hero_stats', [
            'hero_id' => $hero->id,
            'hp' => 1500,
            'attack' => 320,
            'defense' => 280,
            'crit_rate' => 50,
            'crit_amp' => null,
        ]);
    }

    public function test_admin_atualiza_stats_existentes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero();
        HeroStat::create(['hero_id' => $hero->id, 'hp' => 1000, 'attack' => 200]);

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->slug}/stats", ['hp' => 2000, 'attack' => 400, 'defense' => 350])
            ->assertRedirect("/heroes/{$hero->slug}");

        $stats = HeroStat::where('hero_id', $hero->id)->first();
        $this->assertSame(2000, $stats->hp);
        $this->assertSame(400, $stats->attack);
        $this->assertSame(350, $stats->defense);
    }

    public function test_payload_vazio_apaga_stats_existentes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero();
        HeroStat::create(['hero_id' => $hero->id, 'hp' => 1000]);

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->slug}/stats", [])
            ->assertRedirect("/heroes/{$hero->slug}");

        $this->assertDatabaseMissing('hero_stats', ['hero_id' => $hero->id]);
    }

    public function test_validacao_rejeita_negativos_e_string(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero();

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->slug}/stats", ['hp' => -10])
            ->assertSessionHasErrors('hp');

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->slug}/stats", ['attack' => 'abc'])
            ->assertSessionHasErrors('attack');
    }

    public function test_validacao_aceita_max_de_hp_e_rejeita_acima(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero();

        $this->actingAs($admin)
            ->patch("/admin/heroes/{$hero->slug}/stats", ['hp' => 1_000_000])
            ->assertSessionHasErrors('hp');
    }

    public function test_404_quando_slug_nao_existe(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/heroes/inexistente/stats')->assertNotFound();
    }

    public function test_show_inclui_stats_no_payload(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        HeroStat::create([
            'hero_id' => $hero->id,
            'hp' => 1500,
            'attack' => 320,
            'defense' => 280,
            'crit_rate' => 50,
        ]);

        $this->actingAs($user)
            ->get("/heroes/{$hero->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('hero.stats.hp', 1500)
                ->where('hero.stats.attack', 320)
                ->where('hero.stats.defense', 280)
                ->where('hero.stats.crit_rate', 50)
                ->where('hero.stats.crit_amp', null)
            );
    }
}
