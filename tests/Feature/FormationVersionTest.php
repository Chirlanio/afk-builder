<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\FormationVersion;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Services\Formations\FormationVersioning;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    private function makeFormation(User $user): Formation
    {
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test Formation',
            'mode' => 'real',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        return $formation;
    }

    private function makeHero(string $slug = 'h1'): Hero
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

    public function test_update_gera_snapshot_da_versao_anterior(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $this->actingAs($user)->patch("/formations/{$formation->id}", [
            'name' => 'Renamed',
            'teams' => [
                ['position' => 1, 'name' => null, 'heroes' => []],
            ],
        ])->assertRedirect();

        $this->assertSame(1, FormationVersion::where('formation_id', $formation->id)->count());
        $version = FormationVersion::where('formation_id', $formation->id)->first();
        $this->assertSame('Test Formation', $version->snapshot['name']); // Snapshot do nome ANTERIOR
    }

    public function test_lista_versoes_da_formacao(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $versioning = app(FormationVersioning::class);
        $versioning->snapshot($formation);
        $versioning->snapshot($formation);

        $response = $this->actingAs($user)->getJson("/formations/{$formation->id}/versions");

        $response->assertOk()
            ->assertJsonStructure(['versions' => [['id', 'created_at', 'team_count', 'hero_count', 'name']]])
            ->assertJsonCount(2, 'versions');
    }

    public function test_outro_user_nao_lista_versoes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $formation = $this->makeFormation($owner);

        $this->actingAs($other)
            ->getJson("/formations/{$formation->id}/versions")
            ->assertForbidden();
    }

    public function test_restaurar_aplica_snapshot(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $hero = $this->makeHero('h1');

        // Estado inicial: nome "Test Formation", sem heróis
        $versioning = app(FormationVersioning::class);
        $versioning->snapshot($formation);
        $versionToRestore = $formation->versions()->first();

        // Modifica formação: novo nome e adiciona herói
        $formation->update(['name' => 'Modified']);
        $formation->teams->first()->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => 1]);

        // Restaura versão original
        $this->actingAs($user)
            ->post("/formations/{$formation->id}/versions/{$versionToRestore->id}/restore")
            ->assertRedirect();

        $formation->refresh();
        $this->assertSame('Test Formation', $formation->name);
        $this->assertCount(0, $formation->teams->first()->teamHeroes);
    }

    public function test_outro_user_nao_pode_restaurar(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $formation = $this->makeFormation($owner);

        $versioning = app(FormationVersioning::class);
        $version = $versioning->snapshot($formation);

        $this->actingAs($other)
            ->post("/formations/{$formation->id}/versions/{$version->id}/restore")
            ->assertForbidden();
    }

    public function test_cap_de_20_versoes(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $versioning = app(FormationVersioning::class);

        for ($i = 0; $i < 25; $i++) {
            $versioning->snapshot($formation);
        }

        $this->assertSame(FormationVersioning::MAX_VERSIONS_PER_FORMATION, $formation->versions()->count());
    }
}
