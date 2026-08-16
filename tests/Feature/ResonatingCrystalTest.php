<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserResonatingCrystal;
use App\Models\UserResource;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\ResonatingCrystalCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResonatingCrystalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redireciona(): void
    {
        $this->get('/crystal')->assertRedirect('/login');
    }

    public function test_show_inicializa_crystal_e_resources(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/crystal')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(240, $props['crystal']['level']);
        $this->assertSame(0, $props['crystal']['strengthen_progress']);
        $this->assertSame(5, $props['crystal']['slots_unlocked']);
        $this->assertCount(0, $props['pentagram']);
        // Sem heros Ascendidos no roster, level_cap = 240
        $this->assertSame(240, $props['crystal']['level_cap']);

        $this->assertDatabaseHas('user_resonating_crystals', ['user_id' => $user->id]);
        $this->assertDatabaseHas('user_resources', ['user_id' => $user->id]);
    }

    public function test_pentagram_lista_top_5_por_level(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $levels = [240, 220, 200, 180, 160, 100];
        foreach ($levels as $i => $level) {
            $hero = $this->makeHero("h{$i}", 'lightbearer', 'tank', 'ascended');
            $user->userHeroes()->create([
                'hero_id' => $hero->id,
                'level' => $level, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
                'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
            ]);
        }

        $response = $this->actingAs($user)->get('/crystal')->assertOk();
        $props = $response->viewData('page')['props'];

        $pentagram = collect($props['pentagram']);
        $this->assertCount(5, $pentagram);
        // Top 5 ordenado DESC por level
        $this->assertSame([240, 220, 200, 180, 160], $pentagram->pluck('level')->all());

        // Heroes ainda não estão todos no 240 → não é upgraded
        $this->assertFalse($props['crystal']['is_upgraded']);
        // Effective level = 5º maior
        $this->assertSame(160, $props['crystal']['effective_level']);
    }

    public function test_is_upgraded_quando_5_heros_no_240(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $hero = $this->makeHero("max{$i}", 'lightbearer', 'tank', 'ascended');
            $user->userHeroes()->create([
                'hero_id' => $hero->id,
                'level' => 240, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star',
                'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
            ]);
        }

        $response = $this->actingAs($user)->get('/crystal')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertTrue($props['crystal']['is_upgraded']);
        // level_cap = 240 + 5*5 = 265
        $this->assertSame(265, $props['crystal']['level_cap']);
        $this->assertTrue($props['crystal']['can_upgrade']);
    }

    public function test_dimensionais_nao_contam_no_level_cap(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        // 3 ascendidos não-dimensionais + 3 ascendidos dimensionais
        for ($i = 0; $i < 3; $i++) {
            $h = $this->makeHero("regular{$i}", 'lightbearer', 'tank', 'ascended');
            $user->userHeroes()->create([
                'hero_id' => $h->id, 'level' => 240, 'ascension_value' => 12,
                'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
            ]);
        }
        for ($i = 0; $i < 3; $i++) {
            $h = $this->makeHero("dim{$i}", 'dimensional', 'tank', 'ascended');
            $user->userHeroes()->create([
                'hero_id' => $h->id, 'level' => 240, 'ascension_value' => 12,
                'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
            ]);
        }

        $cap = UserResonatingCrystal::levelCapForAccount($user->defaultGameAccount);
        // 240 + 3*5 = 255 (3 non-dim ascendidos)
        $this->assertSame(255, $cap);
    }

    public function test_update_aceita_sync_de_level(): void
    {
        $this->seedCatalog();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/crystal', ['level' => 260, 'strengthen_progress' => 7, 'slots_unlocked' => 8])
            ->assertOk()
            ->assertJsonPath('crystal.level', 260)
            ->assertJsonPath('crystal.strengthen_progress', 7)
            ->assertJsonPath('crystal.slots_unlocked', 8);

        $this->assertDatabaseHas('user_resonating_crystals', [
            'user_id' => $user->id, 'level' => 260, 'strengthen_progress' => 7, 'slots_unlocked' => 8,
        ]);
    }

    public function test_update_rejeita_level_abaixo_de_240(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/crystal', ['level' => 200])
            ->assertStatus(422);
    }

    public function test_strengthen_falha_sem_recursos(): void
    {
        $this->seedCatalog();
        $this->seed(ResonatingCrystalCostsSeeder::class);

        $user = User::factory()->create();

        // Hero ascendido (para destravar can_upgrade)
        $h = $this->makeHero('asc', 'lightbearer', 'tank', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $h->id, 'level' => 240, 'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        $this->actingAs($user)
            ->postJson('/crystal/strengthen')
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_resources');
    }

    public function test_strengthen_incrementa_progress_e_debita_recursos(): void
    {
        $this->seedCatalog();
        $this->seed(ResonatingCrystalCostsSeeder::class);
        $user = User::factory()->create();

        // Hero ascendido
        $h = $this->makeHero('asc', 'lightbearer', 'tank', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $h->id, 'level' => 240, 'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        // Garante recursos suficientes pra 1 fortalecimento do nível 241
        UserResource::create([
            'user_id' => $user->id,
            'gold' => 1_000_000_000,
            'hero_exp' => 1_000_000_000,
            'hero_essence' => 100_000,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/crystal/strengthen')
            ->assertOk();

        $this->assertSame(1, $response->json('crystal.strengthen_progress'));
        $this->assertSame(240, $response->json('crystal.level'));
    }

    public function test_strengthen_10x_sobe_level_e_zera_progress(): void
    {
        $this->seedCatalog();
        $this->seed(ResonatingCrystalCostsSeeder::class);
        $user = User::factory()->create();

        $h = $this->makeHero('asc', 'lightbearer', 'tank', 'ascended');
        $user->userHeroes()->create([
            'hero_id' => $h->id, 'level' => 240, 'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0,
        ]);

        UserResource::create([
            'user_id' => $user->id,
            'gold' => 1_000_000_000,
            'hero_exp' => 100_000_000_000,
            'hero_essence' => 1_000_000,
        ]);

        // 10 fortalecimentos consecutivos
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/crystal/strengthen')->assertOk();
        }

        $crystal = $user->resonatingCrystal()->first();
        $this->assertSame(241, $crystal->level);
        $this->assertSame(0, $crystal->strengthen_progress);
    }

    private function seedCatalog(): void
    {
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
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
