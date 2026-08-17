<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Guide;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            ChallengeTypeSeeder::class,
        ]);
    }

    private function makeHero(string $slug, string $name): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => $name,
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_guest_recebe_401(): void
    {
        $this->getJson('/search?q=test')->assertStatus(401);
    }

    public function test_query_curta_retorna_resultados_vazios(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran', 'Thoran');

        $response = $this->actingAs($user)
            ->getJson('/search?q=t')
            ->assertOk();

        $this->assertSame([], $response->json('results.heroes'));
    }

    public function test_busca_heroi_por_name(): void
    {
        $user = User::factory()->create();
        $this->makeHero('thoran', 'Thoran');
        $this->makeHero('lucius', 'Lucius');

        $response = $this->actingAs($user)
            ->getJson('/search?q=thoran')
            ->assertOk();

        $heroes = $response->json('results.heroes');
        $this->assertCount(1, $heroes);
        $this->assertSame('Thoran', $heroes[0]['label']);
        $this->assertStringContainsString('open=thoran', $heroes[0]['url']);
    }

    public function test_nao_retorna_heroi_pending_ou_inativo(): void
    {
        $user = User::factory()->create();

        Hero::create([
            'slug' => 'pending-hero',
            'name' => 'Pending Hero',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => true,
        ]);
        Hero::create([
            'slug' => 'inactive-hero',
            'name' => 'Inactive Hero',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => false,
            'pending_review' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/search?q=hero')
            ->assertOk();

        $this->assertSame([], $response->json('results.heroes'));
    }

    public function test_busca_formacao_apenas_do_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $challenge = ChallengeType::where('code', 'campaign')->first();

        $owner->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challenge->id,
            'name' => 'Counter Wilders',
            'mode' => 'target',
            'teams_count' => 1,
        ]);
        $other->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challenge->id,
            'name' => 'Counter Wilders',
            'mode' => 'target',
            'teams_count' => 1,
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/search?q=Counter')
            ->assertOk();

        $formations = $response->json('results.formations');
        $this->assertCount(1, $formations);
        $this->assertSame('Counter Wilders', $formations[0]['label']);
    }

    public function test_busca_tier_list_apenas_do_user(): void
    {
        $owner = User::factory()->create();
        $owner->defaultGameAccount->tierLists()->create([
            'name' => 'Minha lista PvP',
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/search?q=PvP')
            ->assertOk();

        $tl = $response->json('results.tier_lists');
        $this->assertCount(1, $tl);
        $this->assertSame('Minha lista PvP', $tl[0]['label']);
    }

    public function test_busca_guide_publico(): void
    {
        $user = User::factory()->create();
        Guide::create([
            'slug' => 'arena-basico',
            'title' => 'Arena Basics',
            'title_pt' => 'Arena do básico',
            'category' => Guide::CATEGORY_MODOS,
            'content' => '...',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/search?q=Arena')
            ->assertOk();

        $guides = $response->json('results.guides');
        $this->assertCount(1, $guides);
        $this->assertSame('Arena do básico', $guides[0]['label']);
    }

    public function test_limite_de_5_resultados_por_categoria(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 7; $i++) {
            $this->makeHero("test-{$i}", "TestHero{$i}");
        }

        $response = $this->actingAs($user)
            ->getJson('/search?q=TestHero')
            ->assertOk();

        $heroes = $response->json('results.heroes');
        $this->assertCount(5, $heroes);
    }
}
