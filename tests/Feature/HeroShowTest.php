<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroFurnitureAbility;
use App\Models\HeroSynergy;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroShowTest extends TestCase
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
            'title' => 'Lightbringer',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_permalink_legacy_redireciona_para_index_com_query(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');

        $this->actingAs($user)
            ->get("/heroes/{$hero->slug}")
            ->assertRedirect('/heroes?open=lucius');
    }

    public function test_endpoint_details_retorna_dados_completos(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonPath('hero.id', $hero->id)
            ->assertJsonPath('hero.slug', 'lucius')
            ->assertJsonPath('hero.name', 'Lucius')
            ->assertJsonStructure([
                'hero' => ['id', 'slug', 'name', 'faction', 'hero_class', 'tier'],
                'userHero',
                'synergies',
                'availableArtifacts',
            ]);
    }

    public function test_details_inclui_user_hero_quando_no_roster(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $userHero = $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
        ]);

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonPath('userHero.id', $userHero->id)
            ->assertJsonPath('userHero.level', 240)
            ->assertJsonPath('userHero.ascension_value', 12);
    }

    public function test_details_lista_synergias_do_heroi(): void
    {
        $user = User::factory()->create();
        $a = $this->makeHero('lucius');
        $b = $this->makeHero('rosaline');
        HeroSynergy::create(['hero_a_id' => $a->id, 'hero_b_id' => $b->id, 'weight' => 15, 'note' => 'Dupla canônica']);

        $this->actingAs($user)
            ->getJson("/heroes/{$a->slug}/details")
            ->assertOk()
            ->assertJsonPath('synergies.0.partner.slug', 'rosaline')
            ->assertJsonPath('synergies.0.weight', 15);
    }

    public function test_details_aponta_para_os_counters_do_heroi(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');

        $url = $this->actingAs($user)->getJson("/heroes/{$hero->slug}/details")->assertOk()->json('countersUrl');

        $this->assertSame('/counters?enemy%5B1%5D='.$hero->id, $url);
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->component('counters/index')
            ->where('enemy.0.hero.id', $hero->id)
        );

        $hero->update(['pending_review' => true]);
        $this->assertNull($this->actingAs($user)->getJson("/heroes/{$hero->slug}/details")->json('countersUrl'));
    }

    public function test_details_404_quando_slug_nao_existe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/heroes/inexistente/details')
            ->assertNotFound();
    }

    public function test_details_inclui_furniture_ability_quando_existe(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        HeroFurnitureAbility::create([
            'hero_id' => $hero->id,
            'name' => "Heaven's Protection",
            'f3_description' => 'F3 desc...',
            'f9_description' => 'F9 desc...',
        ]);

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonPath('hero.furniture_ability.name', "Heaven's Protection")
            ->assertJsonPath('hero.furniture_ability.f3_description', 'F3 desc...');
    }

    public function test_details_furniture_ability_null_quando_nao_existe(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('rosaline');

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonPath('hero.furniture_ability', null);
    }

    public function test_details_404_quando_heroi_inativo(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $hero->update(['is_active' => false]);

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertNotFound();
    }
}
