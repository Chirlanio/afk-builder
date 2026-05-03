<?php

namespace Tests\Feature;

use App\Models\Artifact;
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

    public function test_show_carrega_artefatos_disponiveis(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius', 'tank');
        $duraArt = $this->makeArtifact('duras-grace', Artifact::KIND_DURA);
        $tankArt = $this->makeArtifact('the-barricade', Artifact::KIND_CLASS, 'tank', 3);
        $mageArt = $this->makeArtifact('windbinder', Artifact::KIND_CLASS, 'mage', 3);

        $this->actingAs($user)
            ->get("/heroes/{$hero->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('availableArtifacts', 2) // Dura's + tank class, NÃO mage
                ->where('availableArtifacts.0.kind', 'dura')
            );
    }

    public function test_show_inclui_artifact_relation_quando_equipado(): void
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
            ->get("/heroes/{$hero->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('userHero.artifact_id', $artifact->id)
                ->where('userHero.artifact_star_level', 4)
                ->where('userHero.artifact.name', 'Duras-grace')
            );
    }
}
