<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_rejeitado(): void
    {
        $this->patchJson('/roster/resources', ['primordial_emblems' => 100])
            ->assertStatus(401);
    }

    public function test_update_cria_registro_quando_nao_existe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/roster/resources', [
                'primordial_emblems' => 500,
                'amplifying_emblems' => 300,
            ])
            ->assertOk()
            ->assertJson([
                'resources' => [
                    'primordial_emblems' => 500,
                    'amplifying_emblems' => 300,
                    'faction_emblems' => 0,
                    'mythic_furniture' => 0,
                    'engraving_scrolls' => 0,
                ],
            ]);

        $this->assertDatabaseHas('user_resources', [
            'game_account_id' => $user->defaultGameAccount->id,
            'primordial_emblems' => 500,
            'amplifying_emblems' => 300,
        ]);
    }

    public function test_update_atualiza_subset_preservando_outros_campos(): void
    {
        $user = User::factory()->create();
        UserResource::create([
            'game_account_id' => $user->defaultGameAccount->id,
            'primordial_emblems' => 200,
            'amplifying_emblems' => 100,
            'mythic_furniture' => 50,
        ]);

        $this->actingAs($user)
            ->patchJson('/roster/resources', ['amplifying_emblems' => 999])
            ->assertOk();

        $this->assertDatabaseHas('user_resources', [
            'game_account_id' => $user->defaultGameAccount->id,
            'primordial_emblems' => 200,    // preservado
            'amplifying_emblems' => 999,    // atualizado
            'mythic_furniture' => 50,       // preservado
        ]);
    }

    public function test_validacao_rejeita_valores_negativos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/roster/resources', ['primordial_emblems' => -10])
            ->assertStatus(422);
    }

    public function test_validacao_rejeita_overflow(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/roster/resources', ['primordial_emblems' => 999_999_999])
            ->assertStatus(422);
    }

    public function test_user_so_atualiza_proprios_recursos(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        UserResource::create(['game_account_id' => $userB->defaultGameAccount->id, 'primordial_emblems' => 100]);

        $this->actingAs($userA)
            ->patchJson('/roster/resources', ['primordial_emblems' => 9000])
            ->assertOk();

        // user B intacto
        $this->assertDatabaseHas('user_resources', ['game_account_id' => $userB->defaultGameAccount->id, 'primordial_emblems' => 100]);
        // user A criado novo
        $this->assertDatabaseHas('user_resources', ['game_account_id' => $userA->defaultGameAccount->id, 'primordial_emblems' => 9000]);
    }
}
