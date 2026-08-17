<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameAccountCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lista_contas_do_user(): void
    {
        $user = User::factory()->create();
        $user->gameAccounts()->create(['name' => 'Alt']);

        $response = $this->actingAs($user)->get('/settings/accounts');

        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/accounts')
                ->has('accounts', 2)
                ->where('accounts.0.is_default', true)
                ->where('accounts.0.name', 'Conta Principal')
                ->where('accounts.1.name', 'Alt'),
        );
    }

    public function test_store_cria_nova_conta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/accounts', [
                'name' => 'Servidor X',
                'server' => 'S123',
            ])
            ->assertRedirect();

        $this->assertSame(2, $user->gameAccounts()->count());
        $this->assertDatabaseHas('game_accounts', [
            'user_id' => $user->id,
            'name' => 'Servidor X',
            'server' => 'S123',
        ]);
    }

    public function test_store_rejeita_nome_duplicado(): void
    {
        $user = User::factory()->create();
        $user->gameAccounts()->create(['name' => 'Alt']);

        $this->actingAs($user)
            ->post('/settings/accounts', ['name' => 'Alt'])
            ->assertSessionHasErrors(['name']);
    }

    public function test_update_renomeia_conta(): void
    {
        $user = User::factory()->create();
        $account = $user->gameAccounts()->create(['name' => 'Alt']);

        $this->actingAs($user)
            ->patch("/settings/accounts/{$account->id}", ['name' => 'Renomeada'])
            ->assertRedirect();

        $this->assertSame('Renomeada', $account->fresh()->name);
    }

    public function test_update_promove_para_default(): void
    {
        $user = User::factory()->create();
        $main = $user->defaultGameAccount;
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $this->actingAs($user)
            ->patch("/settings/accounts/{$alt->id}", [
                'name' => 'Alt',
                'is_default' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($alt->fresh()->is_default);
        $this->assertFalse($main->fresh()->is_default);
    }

    public function test_update_rejeita_conta_de_outro_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = $userB->defaultGameAccount;

        $this->actingAs($userA)
            ->patch("/settings/accounts/{$accountB->id}", ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_destroy_remove_conta_secundaria(): void
    {
        $user = User::factory()->create();
        $alt = $user->gameAccounts()->create(['name' => 'Alt']);

        $this->actingAs($user)
            ->delete("/settings/accounts/{$alt->id}")
            ->assertRedirect();

        $this->assertSame(1, $user->gameAccounts()->count());
    }

    public function test_destroy_rejeita_ultima_conta(): void
    {
        $user = User::factory()->create();
        $default = $user->defaultGameAccount;

        $this->actingAs($user)
            ->delete("/settings/accounts/{$default->id}")
            ->assertSessionHasErrors(['delete']);

        $this->assertSame(1, $user->gameAccounts()->count());
    }

    public function test_destroy_rejeita_conta_de_outro_user(): void
    {
        $userA = User::factory()->create();
        $userA->gameAccounts()->create(['name' => 'A2']);
        $userB = User::factory()->create();
        $accountB = $userB->defaultGameAccount;

        $this->actingAs($userA)
            ->delete("/settings/accounts/{$accountB->id}")
            ->assertForbidden();
    }
}
