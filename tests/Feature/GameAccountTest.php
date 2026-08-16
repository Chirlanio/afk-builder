<?php

namespace Tests\Feature;

use App\Models\GameAccount;
use App\Models\User;
use App\Services\ActiveGameAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_primeira_conta_criada_vira_default(): void
    {
        $user = User::factory()->create();

        $account = $user->gameAccounts()->create(['name' => 'Main']);

        $this->assertTrue($account->fresh()->is_default);
    }

    public function test_segunda_conta_criada_nao_vira_default(): void
    {
        $user = User::factory()->create();
        $user->gameAccounts()->create(['name' => 'Main']);

        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $this->assertFalse($alt->fresh()->is_default);
    }

    public function test_marcar_conta_como_default_desmarca_a_anterior(): void
    {
        $user = User::factory()->create();
        $main = $user->gameAccounts()->create(['name' => 'Main']);
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $alt->update(['is_default' => true]);

        $this->assertFalse($main->fresh()->is_default);
        $this->assertTrue($alt->fresh()->is_default);
    }

    public function test_deletar_default_promove_a_proxima_conta(): void
    {
        $user = User::factory()->create();
        $main = $user->gameAccounts()->create(['name' => 'Main']);
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $main->delete();

        $this->assertTrue($alt->fresh()->is_default);
    }

    public function test_active_game_account_faz_fallback_para_default(): void
    {
        $user = User::factory()->create();
        $default = $user->gameAccounts()->create(['name' => 'Main']);
        $user->gameAccounts()->create(['name' => 'Alt']);

        $this->actingAs($user)->get('/dashboard');

        $this->assertEquals(
            $default->id,
            session(ActiveGameAccount::SESSION_KEY),
        );
    }

    public function test_switch_troca_active_game_account(): void
    {
        $user = User::factory()->create();
        $user->gameAccounts()->create(['name' => 'Main']);
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $this->actingAs($user)
            ->post(route('game-accounts.switch', $alt))
            ->assertRedirect();

        $this->assertEquals($alt->id, session(ActiveGameAccount::SESSION_KEY));
    }

    public function test_switch_rejeita_conta_de_outro_user(): void
    {
        $userA = User::factory()->create();
        $userA->gameAccounts()->create(['name' => 'A']);
        $userB = User::factory()->create();
        $accountB = $userB->gameAccounts()->create(['name' => 'B']);

        $this->actingAs($userA)
            ->post(route('game-accounts.switch', $accountB))
            ->assertForbidden();
    }

    public function test_inertia_shared_expoe_game_account_para_user_autenticado(): void
    {
        $user = User::factory()->create();
        $user->gameAccounts()->create(['name' => 'Main']);
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->has('gameAccount.active')
                ->has('gameAccount.accounts', 2)
                ->where('gameAccount.active.name', 'Main')
        );
    }
}
