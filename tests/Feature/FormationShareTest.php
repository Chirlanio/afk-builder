<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Formation;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationShareTest extends TestCase
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

    public function test_dono_gera_share_token(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $this->assertNull($formation->share_token);

        $response = $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/share");

        $response->assertOk()
            ->assertJsonStructure(['share_token', 'public_url']);

        $formation->refresh();
        $this->assertNotNull($formation->share_token);
    }

    public function test_outro_usuario_nao_pode_compartilhar(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $formation = $this->makeFormation($owner);

        $this->actingAs($other)
            ->postJson("/formations/{$formation->id}/share")
            ->assertForbidden();

        $formation->refresh();
        $this->assertNull($formation->share_token);
    }

    public function test_dono_revoga_share_token(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $formation->update(['share_token' => str_repeat('a', 32)]);

        $this->actingAs($user)
            ->deleteJson("/formations/{$formation->id}/share")
            ->assertOk();

        $formation->refresh();
        $this->assertNull($formation->share_token);
    }

    public function test_rota_publica_abre_formacao_compartilhada_sem_auth(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $token = str_repeat('b', 32);
        $formation->update(['share_token' => $token]);

        $this->get("/f/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('formations/public')
                ->where('formation.id', $formation->id)
                ->where('formation.name', 'Test Formation')
            );
    }

    public function test_rota_publica_404_com_token_invalido(): void
    {
        $this->get('/f/'.str_repeat('z', 32))->assertNotFound();
    }

    public function test_segunda_chamada_de_share_retorna_mesmo_token(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $first = $this->actingAs($user)->postJson("/formations/{$formation->id}/share");
        $token1 = $first->json('share_token');

        $second = $this->actingAs($user)->postJson("/formations/{$formation->id}/share");
        $token2 = $second->json('share_token');

        $this->assertSame($token1, $token2);
    }
}
