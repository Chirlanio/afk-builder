<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Formation;
use App\Models\ScoringWeightPreset;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringWeightPresetTest extends TestCase
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

    private function validWeights(): array
    {
        return [
            'power_weight' => 2.0,
            'synergy_weight' => 1.5,
            'faction_bonus_weight' => 1.0,
            'class_balance_weight' => 1.0,
            'tier_rating_weight' => 1.0,
            'feature_weight' => 0.5,
            'furniture_effect_weight' => 0.5,
        ];
    }

    public function test_lista_apenas_presets_do_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'Meu PvP',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);
        ScoringWeightPreset::create([
            'game_account_id' => $other->defaultGameAccount->id,
            'name' => 'Outro user',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);

        $response = $this->actingAs($user)->getJson('/scoring-weight-presets')->assertOk();
        $presets = $response->json('presets');

        $this->assertCount(1, $presets);
        $this->assertSame('Meu PvP', $presets[0]['name']);
    }

    public function test_cria_preset_valido(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/scoring-weight-presets', [
                'name' => 'PvP agressivo',
                'challenge_type_id' => null,
                'weights' => $this->validWeights(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('preset.name', 'PvP agressivo');

        // JSON serializa 2.0 como 2; compara via float cast pra ignorar a diferença.
        $this->assertSame(2.0, (float) $response->json('preset.weights.power_weight'));

        $this->assertDatabaseHas('scoring_weight_presets', [
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'PvP agressivo',
        ]);
    }

    public function test_rejeita_nome_duplicado_no_mesmo_user(): void
    {
        $user = User::factory()->create();
        ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'Dup',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);

        $this->actingAs($user)
            ->postJson('/scoring-weight-presets', [
                'name' => 'Dup',
                'weights' => $this->validWeights(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_dois_users_podem_ter_presets_com_mesmo_nome(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->postJson('/scoring-weight-presets', [
                'name' => 'Compartilhado',
                'weights' => $this->validWeights(),
            ])
            ->assertStatus(201);

        $this->actingAs($userB)
            ->postJson('/scoring-weight-presets', [
                'name' => 'Compartilhado',
                'weights' => $this->validWeights(),
            ])
            ->assertStatus(201);
    }

    public function test_rejeita_peso_fora_do_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/scoring-weight-presets', [
                'name' => 'Inválido',
                'weights' => array_merge($this->validWeights(), ['power_weight' => 5.0]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights.power_weight']);
    }

    public function test_rejeita_challenge_type_inexistente(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/scoring-weight-presets', [
                'name' => 'X',
                'challenge_type_id' => 99999,
                'weights' => $this->validWeights(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_type_id']);
    }

    public function test_filtra_pesos_invalidos_silenciosamente(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/scoring-weight-presets', [
                'name' => 'Limpo',
                'weights' => array_merge($this->validWeights(), ['malicious_key' => 99]),
            ])
            ->assertStatus(201);

        $preset = ScoringWeightPreset::where('name', 'Limpo')->first();
        $this->assertArrayNotHasKey('malicious_key', $preset->weights);
        $this->assertCount(7, $preset->weights);
    }

    public function test_atualiza_preset_proprio(): void
    {
        $user = User::factory()->create();
        $preset = ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'Antigo',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);

        $newWeights = array_merge($this->validWeights(), ['power_weight' => 0.5]);

        $this->actingAs($user)
            ->patchJson("/scoring-weight-presets/{$preset->id}", [
                'name' => 'Renomeado',
                'weights' => $newWeights,
            ])
            ->assertOk()
            ->assertJsonPath('preset.name', 'Renomeado')
            ->assertJsonPath('preset.weights.power_weight', 0.5);
    }

    public function test_nao_atualiza_preset_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $preset = ScoringWeightPreset::create([
            'game_account_id' => $owner->defaultGameAccount->id,
            'name' => 'Privado',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);

        $this->actingAs($other)
            ->patchJson("/scoring-weight-presets/{$preset->id}", [
                'name' => 'Hackeado',
                'weights' => $this->validWeights(),
            ])
            ->assertStatus(403);
    }

    public function test_deleta_preset_proprio(): void
    {
        $user = User::factory()->create();
        $preset = ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'Pra deletar',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);

        $this->actingAs($user)
            ->deleteJson("/scoring-weight-presets/{$preset->id}")
            ->assertOk();

        $this->assertDatabaseMissing('scoring_weight_presets', ['id' => $preset->id]);
    }

    public function test_nao_deleta_preset_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $preset = ScoringWeightPreset::create([
            'game_account_id' => $owner->defaultGameAccount->id,
            'name' => 'Privado',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);

        $this->actingAs($other)
            ->deleteJson("/scoring-weight-presets/{$preset->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('scoring_weight_presets', ['id' => $preset->id]);
    }

    public function test_edit_payload_inclui_presets_filtrados_por_challenge_type(): void
    {
        $user = User::factory()->create();
        $campaign = ChallengeType::where('code', 'campaign')->first();
        $arena = ChallengeType::where('code', 'arena_of_heroes')->first();

        // 3 presets: 1 global, 1 do challenge type da formation (campaign), 1 de outro challenge type
        ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'Global',
            'challenge_type_id' => null,
            'weights' => $this->validWeights(),
        ]);
        ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'Campaign-specific',
            'challenge_type_id' => $campaign->id,
            'weights' => $this->validWeights(),
        ]);
        ScoringWeightPreset::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'name' => 'Arena-only',
            'challenge_type_id' => $arena->id,
            'weights' => $this->validWeights(),
        ]);

        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $campaign->id,
            'name' => 'F',
            'mode' => 'target',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $response = $this->actingAs($user)->get("/formations/{$formation->id}/edit")->assertOk();
        $presets = $response->viewData('page')['props']['weightPresets'];

        // Esperado: Global + Campaign-specific (arena fica fora)
        $names = collect($presets)->pluck('name')->all();
        $this->assertCount(2, $presets);
        $this->assertContains('Global', $names);
        $this->assertContains('Campaign-specific', $names);
        $this->assertNotContains('Arena-only', $names);
    }
}
