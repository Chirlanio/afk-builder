<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_locale_default_e_en(): void
    {
        $user = User::factory()->create();
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_endpoint_atualiza_locale_para_pt(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->patch('/settings/locale', ['locale' => 'pt'])
            ->assertRedirect();

        $this->assertSame('pt', $user->fresh()->locale);
    }

    public function test_endpoint_rejeita_locale_invalido(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/locale', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_guest_nao_pode_alterar_locale(): void
    {
        $this->patch('/settings/locale', ['locale' => 'pt'])
            ->assertRedirect('/login');
    }
}
