<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Formation;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationScoringWeightsTest extends TestCase
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
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'target',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        return $formation;
    }

    public function test_persiste_overrides_validos(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $this->actingAs($user)
            ->patchJson("/formations/{$formation->id}/scoring-weights", [
                'weights' => [
                    'power_weight' => 2.5,
                    'synergy_weight' => 0.5,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('scoring_weights_override.power_weight', 2.5)
            ->assertJsonPath('scoring_weights_override.synergy_weight', 0.5);

        $this->assertDatabaseHas('formations', ['id' => $formation->id]);
        $this->assertSame(2.5, $formation->fresh()->scoring_weights_override['power_weight']);
    }

    public function test_omitir_weights_limpa_override(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $formation->update(['scoring_weights_override' => ['power_weight' => 1.5]]);

        $this->actingAs($user)
            ->patchJson("/formations/{$formation->id}/scoring-weights", ['weights' => null])
            ->assertOk()
            ->assertJsonPath('scoring_weights_override', null);

        $this->assertNull($formation->fresh()->scoring_weights_override);
    }

    public function test_array_vazio_tambem_limpa(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $formation->update(['scoring_weights_override' => ['power_weight' => 1.5]]);

        $this->actingAs($user)
            ->patchJson("/formations/{$formation->id}/scoring-weights", ['weights' => []])
            ->assertOk();

        $this->assertNull($formation->fresh()->scoring_weights_override);
    }

    public function test_rejeita_peso_acima_de_3(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $this->actingAs($user)
            ->patchJson("/formations/{$formation->id}/scoring-weights", [
                'weights' => ['power_weight' => 5.0],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights.power_weight']);
    }

    public function test_rejeita_peso_negativo(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $this->actingAs($user)
            ->patchJson("/formations/{$formation->id}/scoring-weights", [
                'weights' => ['power_weight' => -1.0],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights.power_weight']);
    }

    public function test_nao_permite_editar_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $formation = $this->makeFormation($owner);

        $this->actingAs($other)
            ->patchJson("/formations/{$formation->id}/scoring-weights", [
                'weights' => ['power_weight' => 2.0],
            ])
            ->assertStatus(403);
    }

    public function test_edit_payload_inclui_scoring_profile_overrides_quando_salvos(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $formation->update(['scoring_weights_override' => ['power_weight' => 2.5]]);

        $response = $this->actingAs($user)->get("/formations/{$formation->id}/edit")->assertOk();

        $overrides = $response->viewData('page')['props']['scoringProfileOverrides'];
        $this->assertSame(2.5, (float) $overrides['power_weight']);
    }

    public function test_edit_overrides_null_quando_nao_salvos(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $response = $this->actingAs($user)->get("/formations/{$formation->id}/edit")->assertOk();

        $this->assertNull($response->viewData('page')['props']['scoringProfileOverrides']);
    }
}
