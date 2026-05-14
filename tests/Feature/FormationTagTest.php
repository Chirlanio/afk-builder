<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Formation;
use App\Models\FormationTag;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationTagTest extends TestCase
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
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeFormation(User $user): Formation
    {
        $challenge = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challenge->id,
            'name' => 'F-'.uniqid(),
            'mode' => 'target',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        return $formation;
    }

    public function test_lista_apenas_tags_do_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        FormationTag::create(['user_id' => $user->id, 'name' => 'minha', 'color' => 'sky']);
        FormationTag::create(['user_id' => $other->id, 'name' => 'outra', 'color' => 'rose']);

        $response = $this->actingAs($user)->getJson('/formation-tags')->assertOk();
        $tags = $response->json('tags');

        $this->assertCount(1, $tags);
        $this->assertSame('minha', $tags[0]['name']);
    }

    public function test_cria_tag(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/formation-tags', ['name' => 'speedrun', 'color' => 'emerald'])
            ->assertStatus(201)
            ->assertJsonPath('tag.name', 'speedrun')
            ->assertJsonPath('tag.color', 'emerald');

        $this->assertDatabaseHas('formation_tags', [
            'user_id' => $user->id,
            'name' => 'speedrun',
            'color' => 'emerald',
        ]);
    }

    public function test_cor_default_quando_omitida(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/formation-tags', ['name' => 'sem-cor'])
            ->assertStatus(201)
            ->assertJsonPath('tag.color', 'amber');
    }

    public function test_rejeita_cor_invalida(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/formation-tags', ['name' => 'x', 'color' => 'neon-pink'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    public function test_rejeita_nome_duplicado_por_user(): void
    {
        $user = User::factory()->create();
        FormationTag::create(['user_id' => $user->id, 'name' => 'dup', 'color' => 'amber']);

        $this->actingAs($user)
            ->postJson('/formation-tags', ['name' => 'dup'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_users_diferentes_podem_ter_nome_igual(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson('/formation-tags', ['name' => 'comum'])->assertStatus(201);
        $this->actingAs($b)->postJson('/formation-tags', ['name' => 'comum'])->assertStatus(201);
    }

    public function test_atualiza_tag_propria(): void
    {
        $user = User::factory()->create();
        $tag = FormationTag::create(['user_id' => $user->id, 'name' => 'antiga', 'color' => 'amber']);

        $this->actingAs($user)
            ->patchJson("/formation-tags/{$tag->id}", ['name' => 'nova', 'color' => 'sky'])
            ->assertOk()
            ->assertJsonPath('tag.name', 'nova')
            ->assertJsonPath('tag.color', 'sky');
    }

    public function test_nao_atualiza_tag_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tag = FormationTag::create(['user_id' => $owner->id, 'name' => 't', 'color' => 'amber']);

        $this->actingAs($other)
            ->patchJson("/formation-tags/{$tag->id}", ['name' => 'hack'])
            ->assertStatus(403);
    }

    public function test_deleta_tag_propria(): void
    {
        $user = User::factory()->create();
        $tag = FormationTag::create(['user_id' => $user->id, 'name' => 'remove-me', 'color' => 'rose']);

        $this->actingAs($user)
            ->deleteJson("/formation-tags/{$tag->id}")
            ->assertOk();

        $this->assertDatabaseMissing('formation_tags', ['id' => $tag->id]);
    }

    public function test_deleta_tag_remove_de_formacoes(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $tag = FormationTag::create(['user_id' => $user->id, 'name' => 'sera-removida', 'color' => 'amber']);
        $formation->tags()->attach($tag->id);

        $this->assertDatabaseHas('formation_tag_assignments', [
            'formation_id' => $formation->id,
            'formation_tag_id' => $tag->id,
        ]);

        $this->actingAs($user)->deleteJson("/formation-tags/{$tag->id}")->assertOk();

        $this->assertDatabaseMissing('formation_tag_assignments', [
            'formation_tag_id' => $tag->id,
        ]);
    }

    public function test_update_formation_sincroniza_tags(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $tagA = FormationTag::create(['user_id' => $user->id, 'name' => 'a', 'color' => 'amber']);
        $tagB = FormationTag::create(['user_id' => $user->id, 'name' => 'b', 'color' => 'sky']);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => $formation->name,
                'tag_ids' => [$tagA->id, $tagB->id],
                'teams' => [['position' => 1, 'name' => null, 'heroes' => []]],
            ])
            ->assertRedirect();

        $this->assertCount(2, $formation->fresh()->tags);
    }

    public function test_update_remove_tag_quando_tag_ids_vazio(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $tag = FormationTag::create(['user_id' => $user->id, 'name' => 'a', 'color' => 'amber']);
        $formation->tags()->attach($tag->id);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => $formation->name,
                'tag_ids' => [],
                'teams' => [['position' => 1, 'name' => null, 'heroes' => []]],
            ])
            ->assertRedirect();

        $this->assertCount(0, $formation->fresh()->tags);
    }

    public function test_update_rejeita_tag_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $formation = $this->makeFormation($owner);
        $foreignTag = FormationTag::create(['user_id' => $other->id, 'name' => 'foreign', 'color' => 'amber']);

        $this->actingAs($owner)
            ->patch("/formations/{$formation->id}", [
                'name' => $formation->name,
                'tag_ids' => [$foreignTag->id],
                'teams' => [['position' => 1, 'name' => null, 'heroes' => []]],
            ])
            ->assertSessionHasErrors(['tag_ids.0']);
    }

    public function test_index_inclui_tags_e_available_tags(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $tag = FormationTag::create(['user_id' => $user->id, 'name' => 'speedrun', 'color' => 'emerald']);
        $formation->tags()->attach($tag->id);
        FormationTag::create(['user_id' => $user->id, 'name' => 'safe', 'color' => 'sky']);

        $response = $this->actingAs($user)->get('/formations')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertCount(2, $props['availableTags']);
        $this->assertSame('speedrun', $props['formations'][0]['tags'][0]['name']);
    }

    public function test_edit_inclui_tags_da_formation_e_available_tags(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $tag = FormationTag::create(['user_id' => $user->id, 'name' => 'meu', 'color' => 'rose']);
        $formation->tags()->attach($tag->id);

        $response = $this->actingAs($user)->get("/formations/{$formation->id}/edit")->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertCount(1, $props['formation']['tags']);
        $this->assertSame('meu', $props['formation']['tags'][0]['name']);
        $this->assertCount(1, $props['availableTags']);
    }
}
