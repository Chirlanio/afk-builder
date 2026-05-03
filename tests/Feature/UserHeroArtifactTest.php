<?php

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\Collection;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHeroArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function makeHero(string $slug = 'lucius', string $classCode = 'tank'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', $classCode)->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    private function makeArtifact(string $slug, string $kind, ?string $classCode = null, int $maxStars = 5): Artifact
    {
        return Artifact::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'kind' => $kind,
            'hero_class_id' => $classCode ? HeroClass::where('code', $classCode)->value('id') : null,
            'attributes' => [['stat' => 'HP', 'value' => '8%']],
            'powers' => [['stars' => 1, 'description' => 'Power 1']],
            'max_stars' => $maxStars,
            'is_active' => true,
        ]);
    }

    public function test_user_pode_equipar_artefato_dura(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $artifact = $this->makeArtifact('duras-grace', Artifact::KIND_DURA);

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'ascension_code' => 'ascended_1_star',
                'artifact_id' => $artifact->id,
                'artifact_star_level' => 3,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->where('hero_id', $hero->id)->first();
        $this->assertNotNull($userHero);
        $this->assertSame($artifact->id, $userHero->artifact_id);
        $this->assertSame(3, $userHero->artifact_star_level);
    }

    public function test_user_pode_equipar_class_artifact_da_classe_correta(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius', 'tank');
        $artifact = $this->makeArtifact('the-barricade', Artifact::KIND_CLASS, 'tank', 3);

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'artifact_id' => $artifact->id,
                'artifact_star_level' => 2,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->first();
        $this->assertSame($artifact->id, $userHero->artifact_id);
        $this->assertSame(2, $userHero->artifact_star_level);
    }

    public function test_class_artifact_de_classe_diferente_e_ignorado(): void
    {
        $user = User::factory()->create();
        $tankHero = $this->makeHero('lucius', 'tank');
        $mageArtifact = $this->makeArtifact('windbinder', Artifact::KIND_CLASS, 'mage', 3);

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $tankHero->id,
                'level' => 240,
                'artifact_id' => $mageArtifact->id,
                'artifact_star_level' => 2,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->first();
        $this->assertNull($userHero->artifact_id);
        $this->assertNull($userHero->artifact_star_level);
    }

    public function test_star_level_e_clamped_no_max_do_artifact(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $artifact = $this->makeArtifact('class-art', Artifact::KIND_CLASS, 'tank', 3);

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'artifact_id' => $artifact->id,
                'artifact_star_level' => 5, // class é max 3
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->first();
        $this->assertSame(3, $userHero->artifact_star_level);
    }

    public function test_validacao_rejeita_artifact_inexistente(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'artifact_id' => 9999,
            ])
            ->assertSessionHasErrors('artifact_id');
    }

    public function test_remover_artifact_setando_null(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $artifact = $this->makeArtifact('duras-grace', Artifact::KIND_DURA);

        $userHero = $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'artifact_id' => $artifact->id,
            'artifact_star_level' => 3,
        ]);

        $this->actingAs($user)
            ->patch("/user/heroes/{$userHero->id}", [
                'level' => 240,
                'artifact_id' => null,
            ])
            ->assertRedirect();

        $userHero->refresh();
        $this->assertNull($userHero->artifact_id);
        $this->assertNull($userHero->artifact_star_level);
    }

    public function test_details_carrega_artefatos_disponiveis(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius', 'tank');
        $this->makeArtifact('duras-grace', Artifact::KIND_DURA);
        $this->makeArtifact('the-barricade', Artifact::KIND_CLASS, 'tank', 3);
        $this->makeArtifact('windbinder', Artifact::KIND_CLASS, 'mage', 3);

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonCount(2, 'availableArtifacts') // Dura's + tank class, NÃO mage
            ->assertJsonPath('availableArtifacts.0.kind', 'dura');
    }

    public function test_user_pode_equipar_collection(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $collection = Collection::create([
            'slug' => 'reapers-talon',
            'name' => "Reaper's Talon",
            'hp_bonus' => 1500,
            'attack_bonus' => 200,
        ]);

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'collection_id' => $collection->id,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->first();
        $this->assertSame($collection->id, $userHero->collection_id);
        $this->assertNull($userHero->artifact_id);
    }

    public function test_collection_sobrescreve_artifact_no_mesmo_payload(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $artifact = $this->makeArtifact('duras-grace', Artifact::KIND_DURA);
        $collection = Collection::create(['slug' => 'rt', 'name' => 'RT']);

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'artifact_id' => $artifact->id,
                'artifact_star_level' => 3,
                'collection_id' => $collection->id,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->first();
        // Coleção tem prioridade — artifact é zerado mesmo quando vem no mesmo payload
        $this->assertSame($collection->id, $userHero->collection_id);
        $this->assertNull($userHero->artifact_id);
        $this->assertNull($userHero->artifact_star_level);
    }

    public function test_artifact_substitui_collection_quando_apenas_artifact_no_payload(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $artifact = $this->makeArtifact('duras-grace', Artifact::KIND_DURA);
        $collection = Collection::create(['slug' => 'rt', 'name' => 'RT']);

        $userHero = $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'collection_id' => $collection->id,
        ]);

        $this->actingAs($user)
            ->patch("/user/heroes/{$userHero->id}", [
                'level' => 240,
                'artifact_id' => $artifact->id,
                'artifact_star_level' => 2,
            ])
            ->assertRedirect();

        $userHero->refresh();
        $this->assertSame($artifact->id, $userHero->artifact_id);
        $this->assertNull($userHero->collection_id);
    }

    public function test_validacao_rejeita_collection_inexistente(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'collection_id' => 9999,
            ])
            ->assertSessionHasErrors('collection_id');
    }

    public function test_details_carrega_collections_disponiveis(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        Collection::create(['slug' => 'a', 'name' => 'Alpha', 'is_active' => true]);
        Collection::create(['slug' => 'b', 'name' => 'Beta', 'is_active' => false]);

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonCount(1, 'availableCollections')
            ->assertJsonPath('availableCollections.0.slug', 'a');
    }

    public function test_user_pode_equipar_4_pecas_de_equipamento(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'weapon_tier' => 'T6', 'weapon_level' => 20,
                'helmet_tier' => 'T3', 'helmet_level' => 10,
                'chest_tier' => 'T5', 'chest_level' => 15,
                'boots_tier' => 'T4', 'boots_level' => 12,
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->first();
        $this->assertSame('T6', $userHero->weapon_tier);
        $this->assertSame(20, $userHero->weapon_level);
        $this->assertSame('T3', $userHero->helmet_tier);
        $this->assertSame(10, $userHero->helmet_level);
        $this->assertSame('T5', $userHero->chest_tier);
        $this->assertSame(15, $userHero->chest_level);
        $this->assertSame('T4', $userHero->boots_tier);
        $this->assertSame(12, $userHero->boots_level);
    }

    public function test_validacao_rejeita_tier_invalido(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'weapon_tier' => 'T99',
                'weapon_level' => 5,
            ])
            ->assertSessionHasErrors('weapon_tier');
    }

    public function test_validacao_rejeita_level_acima_de_20(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'weapon_tier' => 'T6',
                'weapon_level' => 25,
            ])
            ->assertSessionHasErrors('weapon_level');
    }

    public function test_tier_sem_level_e_normalizado_para_null(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();

        $this->actingAs($user)
            ->post('/user/heroes', [
                'hero_id' => $hero->id,
                'level' => 240,
                'weapon_tier' => 'T3',
                // sem weapon_level
            ])
            ->assertRedirect();

        $userHero = UserHero::where('user_id', $user->id)->first();
        // Sem level, controller normaliza tier pra null
        $this->assertNull($userHero->weapon_tier);
        $this->assertNull($userHero->weapon_level);
    }

    public function test_details_inclui_artifact_relation_quando_equipado(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $artifact = $this->makeArtifact('duras-grace', Artifact::KIND_DURA);

        $user->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'artifact_id' => $artifact->id,
            'artifact_star_level' => 4,
        ]);

        $this->actingAs($user)
            ->getJson("/heroes/{$hero->slug}/details")
            ->assertOk()
            ->assertJsonPath('userHero.artifact_id', $artifact->id)
            ->assertJsonPath('userHero.artifact_star_level', 4)
            ->assertJsonPath('userHero.artifact.name', 'Duras-grace');
    }
}
