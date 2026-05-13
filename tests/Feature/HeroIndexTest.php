<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroFeature;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function makeHero(string $slug, ?int $factionId = null): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => $factionId ?? Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_payload_inclui_features_carregadas(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        HeroFeature::create([
            'hero_id' => $hero->id,
            'tag' => HeroFeature::TAG_PVP_TOP,
            'note' => 'S-tier defender',
            'source' => 'afk.guide',
        ]);
        HeroFeature::create([
            'hero_id' => $hero->id,
            'tag' => HeroFeature::TAG_EARLY_TANK,
            'source' => 'afk.guide',
        ]);

        $response = $this->actingAs($user)
            ->get('/heroes')
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('heroes/index')
            ->has('heroes', 1)
            ->has('heroes.0.features', 2)
        );

        $tags = collect($response->viewData('page')['props']['heroes'][0]['features'])->pluck('tag')->all();
        $this->assertContains(HeroFeature::TAG_PVP_TOP, $tags);
        $this->assertContains(HeroFeature::TAG_EARLY_TANK, $tags);
    }

    public function test_hero_sem_features_retorna_array_vazio(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran');

        $this->actingAs($user)
            ->get('/heroes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('heroes/index')
                ->has('heroes.0.features', 0)
            );
    }
}
