<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\Collection;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroFeature;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
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

    public function test_roster_payload_inclui_campos_de_equipamento(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('thoran');
        $artifact = Artifact::create([
            'slug' => 'dura-call',
            'name' => 'Dura Call',
            'kind' => Artifact::KIND_DURA,
            'is_active' => true,
        ]);
        $collection = Collection::create([
            'slug' => 'set1',
            'name' => 'Set 1',
            'hp_bonus' => 10,
            'attack_bonus' => 5,
            'defense_bonus' => 5,
            'is_active' => true,
        ]);

        UserHero::create([
            'user_id' => $user->id,
            'hero_id' => $hero->id,
            'level' => 220,
            'ascension_value' => 16,
            'ascension_code' => 'ascended_5_stars',
            'signature_item' => 30,
            'furniture' => 9,
            'engraving' => 80,
            'artifact_id' => $artifact->id,
            'collection_id' => $collection->id,
        ]);

        $response = $this->actingAs($user)->get('/heroes')->assertOk();
        $roster = $response->viewData('page')['props']['roster'];

        // Roster vem keyado por hero_id (Eloquent Collection serializa preservando keys)
        $entry = $roster[$hero->id];
        $this->assertSame(30, $entry['signature_item']);
        $this->assertSame(9, $entry['furniture']);
        $this->assertSame(80, $entry['engraving']);
        $this->assertSame(220, $entry['level']);
        $this->assertSame($artifact->id, $entry['artifact_id']);
        $this->assertSame($collection->id, $entry['collection_id']);
    }

    public function test_roster_isolado_por_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $hero = $this->makeHero('granit');

        UserHero::create([
            'user_id' => $userA->id,
            'hero_id' => $hero->id,
            'level' => 200,
            'ascension_value' => 12,
            'ascension_code' => 'mythic',
            'signature_item' => 0,
            'furniture' => 0,
            'engraving' => 0,
        ]);

        $response = $this->actingAs($userB)->get('/heroes')->assertOk();
        $roster = $response->viewData('page')['props']['roster'];

        $this->assertArrayNotHasKey($hero->id, $roster);
    }
}
