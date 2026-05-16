<?php

namespace Tests\Feature\Admin;

use App\Models\Beast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeastAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redireciona_para_login(): void
    {
        $this->get('/admin/beasts')->assertRedirect('/login');
    }

    public function test_nao_admin_recebe_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/beasts')->assertForbidden();
        $this->actingAs($user)->postJson('/admin/beasts', [])->assertForbidden();
    }

    public function test_admin_lista_beasts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Beast::create(['slug' => 'dreary-ball', 'name' => 'Dreary Ball']);

        $this->actingAs($admin)
            ->get('/admin/beasts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/beasts/index')
                ->has('beasts', 1)
                ->where('beasts.0.slug', 'dreary-ball')
            );
    }

    public function test_admin_cria_beast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/beasts', [
                'slug' => 'dreary-ball',
                'name' => 'Dreary Ball',
                'description' => 'Buffa todos os heróis.',
                'buffs_strength' => ['attack' => 17.59, 'defense' => 47.55],
                'buffs_team' => ['hp' => 14.03, 'attack' => 10.24],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $beast = Beast::where('slug', 'dreary-ball')->first();
        $this->assertNotNull($beast);
        $this->assertEqualsWithDelta(17.59, $beast->buffs_strength['attack'], 0.01);
        $this->assertEqualsWithDelta(47.55, $beast->buffs_strength['defense'], 0.01);
        $this->assertEqualsWithDelta(14.03, $beast->buffs_team['hp'], 0.01);
    }

    public function test_validacao_slug_unico(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Beast::create(['slug' => 'dreary-ball', 'name' => 'X']);

        $this->actingAs($admin)
            ->postJson('/admin/beasts', ['slug' => 'dreary-ball', 'name' => 'Outro'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_admin_atualiza_beast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $beast = Beast::create(['slug' => 'dreary-ball', 'name' => 'Old']);

        $this->actingAs($admin)
            ->patchJson("/admin/beasts/{$beast->id}", [
                'slug' => 'dreary-ball',
                'name' => 'New',
                'buffs_strength' => ['attack' => 25.5],
            ])
            ->assertOk();

        $this->assertSame('New', $beast->fresh()->name);
        $this->assertEqualsWithDelta(25.5, $beast->fresh()->buffs_strength['attack'], 0.01);
    }

    public function test_admin_remove_beast(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $beast = Beast::create(['slug' => 'x', 'name' => 'X']);

        $this->actingAs($admin)
            ->delete("/admin/beasts/{$beast->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('beasts', ['id' => $beast->id]);
    }
}
