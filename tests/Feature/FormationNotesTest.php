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
use Illuminate\Support\Str;
use Tests\TestCase;

class FormationNotesTest extends TestCase
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

    private function makeFormation(User $user, array $overrides = []): Formation
    {
        $challengeType = ChallengeType::where('code', 'campaign')->first();
        $formation = $user->formations()->create(array_merge([
            'challenge_type_id' => $challengeType->id,
            'name' => 'Test',
            'mode' => 'target',
            'teams_count' => 1,
        ], $overrides));
        $formation->teams()->create(['position' => 1]);

        return $formation;
    }

    public function test_update_aceita_notas_markdown(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $notes = "# Estratégia\n\n- Tank na frente\n- **CC** contra Skreg";

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => $formation->name,
                'notes' => $notes,
                'teams' => [['position' => 1, 'name' => null, 'heroes' => []]],
            ])
            ->assertRedirect();

        $this->assertSame($notes, $formation->fresh()->notes);
    }

    public function test_update_aceita_notes_null(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $formation->update(['notes' => 'algo antigo']);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => $formation->name,
                'notes' => null,
                'teams' => [['position' => 1, 'name' => null, 'heroes' => []]],
            ])
            ->assertRedirect();

        $this->assertNull($formation->fresh()->notes);
    }

    public function test_update_rejeita_notes_acima_de_2000_chars(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $longNotes = str_repeat('a', 2001);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => $formation->name,
                'notes' => $longNotes,
                'teams' => [['position' => 1, 'name' => null, 'heroes' => []]],
            ])
            ->assertSessionHasErrors(['notes']);
    }

    public function test_pagina_publica_mostra_notas(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $formation->update([
            'notes' => "# Como usar\nMantenha o tank vivo.",
            'share_token' => Str::random(32),
        ]);

        $response = $this->get("/f/{$formation->share_token}")->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame("# Como usar\nMantenha o tank vivo.", $props['formation']['notes']);
    }

    public function test_edit_payload_inclui_notes_atuais(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $formation->update(['notes' => 'minhas anotações']);

        $response = $this->actingAs($user)->get("/formations/{$formation->id}/edit")->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('minhas anotações', $props['formation']['notes']);
    }

    public function test_outro_user_nao_atualiza_notes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $formation = $this->makeFormation($owner);

        $this->actingAs($other)
            ->patch("/formations/{$formation->id}", [
                'name' => $formation->name,
                'notes' => 'invasão',
                'teams' => [['position' => 1, 'name' => null, 'heroes' => []]],
            ])
            ->assertStatus(403);
    }
}
