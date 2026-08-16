<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActiveGameAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_criar_user_cria_conta_principal_automaticamente(): void
    {
        $user = User::factory()->create();

        $accounts = $user->gameAccounts()->get();

        $this->assertCount(1, $accounts);
        $this->assertSame('Conta Principal', $accounts->first()->name);
        $this->assertTrue($accounts->first()->is_default);
    }

    public function test_segunda_conta_criada_nao_vira_default(): void
    {
        $user = User::factory()->create();

        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $this->assertFalse($alt->fresh()->is_default);
    }

    public function test_marcar_conta_como_default_desmarca_a_anterior(): void
    {
        $user = User::factory()->create();
        $main = $user->defaultGameAccount;
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $alt->update(['is_default' => true]);

        $this->assertFalse($main->fresh()->is_default);
        $this->assertTrue($alt->fresh()->is_default);
    }

    public function test_deletar_default_promove_a_proxima_conta(): void
    {
        $user = User::factory()->create();
        $default = $user->defaultGameAccount;
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $default->delete();

        $this->assertTrue($alt->fresh()->is_default);
    }

    public function test_active_game_account_faz_fallback_para_default(): void
    {
        $user = User::factory()->create();
        $default = $user->defaultGameAccount;
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
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $this->actingAs($user)
            ->post(route('game-accounts.switch', $alt))
            ->assertRedirect();

        $this->assertEquals($alt->id, session(ActiveGameAccount::SESSION_KEY));
    }

    public function test_switch_rejeita_conta_de_outro_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = $userB->defaultGameAccount;

        $this->actingAs($userA)
            ->post(route('game-accounts.switch', $accountB))
            ->assertForbidden();
    }

    public function test_inertia_shared_expoe_game_account_para_user_autenticado(): void
    {
        $user = User::factory()->create();
        $user->gameAccounts()->create(['name' => 'Alt']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->has('gameAccount.active')
                ->has('gameAccount.accounts', 2)
                ->where('gameAccount.active.name', 'Conta Principal')
        );
    }
}
