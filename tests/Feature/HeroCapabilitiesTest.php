<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Services\Optimization\HeroCapabilityClassifier as Cap;
use App\Services\Optimization\PowerCalculator;
use App\Services\Optimization\ScoringContext;
use App\Services\Optimization\ScoringProfile;
use App\Services\Optimization\TeamScorer;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function makeHero(string $slug, string $class = 'support', ?string $role = null, array $abilities = []): Hero
    {
        $hero = Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', $class)->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'role_label' => $role,
            'is_active' => true,
            'pending_review' => false,
        ]);
        foreach ($abilities as $i => $text) {
            $hero->abilities()->create(['name' => "Skill {$i}", 'type' => 'active', 'display_order' => $i, 'description' => $text]);
        }

        return $hero;
    }

    public function test_comando_gera_perfil_e_capacidades_por_habilidade(): void
    {
        $hero = $this->makeHero('lucius', 'tank', 'Regen', [
            'Lucius blesses all of his allies with a divine shield that reduces damage equal to 500% of his attack rating.',
            'Lucius smashes the ground, knocking nearby enemies over, while also dealing 130% AoE damage to them.',
        ]);

        $this->artisan('heroes:classify-capabilities')->assertSuccessful();

        $hero->refresh();
        $this->assertArrayHasKey(Cap::SHIELD, $hero->capabilities);
        $this->assertArrayHasKey(Cap::CC_HARD, $hero->capabilities);
        $this->assertArrayHasKey(Cap::HEALING, $hero->capabilities); // papel "Regen"
        $this->assertArrayHasKey(Cap::FRONTLINE, $hero->capabilities); // classe tank
        $this->assertContains(Cap::SHIELD, $hero->abilities()->first()->effects);
    }

    public function test_reclassificar_preserva_correcoes_do_admin(): void
    {
        $hero = $this->makeHero('brutus', 'tank');
        $hero->update(['capabilities_manual' => ['add' => [Cap::SELF_SUSTAIN => 2]]]);

        $this->artisan('heroes:classify-capabilities')->assertSuccessful();

        $hero->refresh();
        $this->assertSame(['add' => [Cap::SELF_SUSTAIN => 2]], $hero->capabilities_manual);
        $this->assertSame(2, $hero->effectiveCapabilities()[Cap::SELF_SUSTAIN]);
        $this->assertSame(1, $hero->effectiveCapabilities()[Cap::FRONTLINE]);
    }

    public function test_admin_salva_so_a_diferenca_em_relacao_ao_classificador(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero('shemira');
        $hero->update(['capabilities' => [Cap::DAMAGE_AOE => 1, Cap::SELF_SUSTAIN => 2]]);

        $this->actingAs($admin)
            ->patchJson("/admin/heroes/{$hero->id}/capabilities", ['capabilities' => [
                Cap::DAMAGE_AOE => 1,          // igual ao classificador → não vira correção
                Cap::SELF_SUSTAIN => 0,        // removida
                Cap::CC_SOFT => '1',           // adicionada (string do form)
            ]])
            ->assertOk()
            ->assertJsonPath('has_manual', true)
            ->assertJsonPath('effective.'.Cap::CC_SOFT, 1);

        $this->assertSame(['add' => [Cap::CC_SOFT => 1], 'remove' => [Cap::SELF_SUSTAIN]], $hero->fresh()->capabilities_manual);
    }

    public function test_admin_volta_ao_classificador(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero('rowan');
        $hero->update(['capabilities' => [Cap::HEALING => 1], 'capabilities_manual' => ['remove' => [Cap::HEALING]]]);

        $this->actingAs($admin)
            ->deleteJson("/admin/heroes/{$hero->id}/capabilities")
            ->assertOk()
            ->assertJsonPath('effective.'.Cap::HEALING, 1);

        $this->assertNull($hero->fresh()->capabilities_manual);
    }

    public function test_admin_rejeita_capacidade_desconhecida_e_forca_invalida(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $hero = $this->makeHero('nara');

        $this->actingAs($admin)
            ->patchJson("/admin/heroes/{$hero->id}/capabilities", ['capabilities' => ['voar' => 1]])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->patchJson("/admin/heroes/{$hero->id}/capabilities", ['capabilities' => [Cap::HEALING => 3]])
            ->assertUnprocessable();
    }

    public function test_nao_admin_nao_acessa_a_revisao(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $hero = $this->makeHero('nara');

        $this->actingAs($user)->get('/admin/heroes/capabilities')->assertForbidden();
        $this->actingAs($user)->patchJson("/admin/heroes/{$hero->id}/capabilities", ['capabilities' => []])->assertForbidden();
    }

    public function test_pagina_de_revisao_lista_os_herois(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->makeHero('lucius')->update(['capabilities' => [Cap::HEALING => 1]]);

        $this->actingAs($admin)
            ->get('/admin/heroes/capabilities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/heroes/capabilities')
                ->has('heroes', 1)
                ->where('heroes.0.effective.'.Cap::HEALING, 1)
                ->has('tags', count(Cap::ALL)));
    }

    public function test_detalhes_do_heroi_trazem_o_perfil_efetivo(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $hero->update(['capabilities' => [Cap::HEALING => 1], 'capabilities_manual' => ['add' => [Cap::SHIELD => 2]]]);

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonPath('hero.effective_capabilities.'.Cap::HEALING, 1)
            ->assertJsonPath('hero.effective_capabilities.'.Cap::SHIELD, 2);
    }

    public function test_analise_do_time_vem_no_breakdown_sem_mudar_o_total(): void
    {
        $healer = $this->makeHero('healer', 'support');
        $tank = $this->makeHero('tank', 'tank');
        $heroes = [$healer->load(['faction', 'heroClass', 'tier']), $tank->load(['faction', 'heroClass', 'tier'])];
        $scorer = new TeamScorer(new PowerCalculator);
        $ctx = new ScoringContext(challengeCode: 'campaign');

        $before = $scorer->score($heroes, [], [], new ScoringProfile, null, $ctx);

        $healer->update(['capabilities' => [Cap::HEALING => 2]]);
        $tank->update(['capabilities' => [Cap::FRONTLINE => 1]]);
        $after = $scorer->score([$healer->fresh(['faction', 'heroClass', 'tier']), $tank->fresh(['faction', 'heroClass', 'tier'])], [], [], new ScoringProfile, null, $ctx);

        $this->assertSame($before->total, $after->total);
        $analysis = $after->details['capabilities'];
        $this->assertTrue($analysis['has_profiles']);
        $this->assertSame(['healer'], $analysis['covered'][Cap::HEALING]);
        $this->assertContains(Cap::CC_HARD, $analysis['missing']);
    }
}
