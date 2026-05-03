<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
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

    public function test_pagina_de_detalhe_renderiza_com_hero_props(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');

        $this->actingAs($user)
            ->get("/heroes/{$hero->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('heroes/show')
                ->where('hero.id', $hero->id)
                ->where('hero.slug', 'lucius')
                ->where('hero.name', 'Lucius')
                ->has('hero.faction')
                ->has('hero.hero_class')
                ->has('hero.tier')
                ->where('userHero', null)
                ->has('synergies')
            );
    }

    public function test_inclui_user_hero_quando_no_roster(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $userHero = $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 12,
            'ascension_code' => 'ascended_1_star',
        ]);

        $this->actingAs($user)
            ->get("/heroes/{$hero->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('userHero.id', $userHero->id)
                ->where('userHero.level', 240)
                ->where('userHero.ascension_value', 12)
            );
    }

    public function test_lista_synergias_do_heroi(): void
    {
        $user = User::factory()->create();
        $a = $this->makeHero('lucius');
        $b = $this->makeHero('rosaline');
        HeroSynergy::create(['hero_a_id' => $a->id, 'hero_b_id' => $b->id, 'weight' => 15, 'note' => 'Dupla canônica']);

        $this->actingAs($user)
            ->get("/heroes/{$a->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('synergies', 1)
                ->where('synergies.0.partner.slug', 'rosaline')
                ->where('synergies.0.weight', 15)
            );
    }

    public function test_404_quando_slug_nao_existe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/heroes/inexistente')
            ->assertNotFound();
    }

    public function test_404_quando_heroi_inativo(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $hero->update(['is_active' => false]);

        $this->actingAs($user)
            ->get("/heroes/{$hero->slug}")
            ->assertNotFound();
    }
}
