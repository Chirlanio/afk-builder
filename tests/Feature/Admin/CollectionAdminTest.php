<?php

namespace Tests\Feature\Admin;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redireciona_para_login(): void
    {
        $this->get('/admin/collections')->assertRedirect('/login');
    }

    public function test_nao_admin_recebe_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/collections')->assertForbidden();
        $this->actingAs($user)->postJson('/admin/collections', [])->assertForbidden();
    }

    public function test_admin_lista_colecoes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Collection::create([
            'slug' => 'reapers-talon',
            'name' => "Reaper's Talon",
            'hp_bonus' => 1500,
            'attack_bonus' => 200,
            'defense_bonus' => 100,
        ]);

        $this->actingAs($admin)
            ->get('/admin/collections')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/collections/index')
                ->has('collections', 1)
                ->where('collections.0.slug', 'reapers-talon')
            );
    }

    public function test_admin_cria_colecao(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/collections', [
                'slug' => 'sinners-rose',
                'name' => "Sinner's Rose",
                'description' => 'Aumenta o dano de skills.',
                'hp_bonus' => 1200,
                'attack_bonus' => 250,
                'defense_bonus' => 80,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('collections', [
            'slug' => 'sinners-rose',
            'name' => "Sinner's Rose",
            'hp_bonus' => 1200,
            'attack_bonus' => 250,
            'defense_bonus' => 80,
        ]);
    }

    public function test_validacao_slug_unico(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Collection::create(['slug' => 'reapers-talon', 'name' => 'X']);

        $this->actingAs($admin)
            ->postJson('/admin/collections', [
                'slug' => 'reapers-talon',
                'name' => 'Outra',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_validacao_slug_pattern(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->postJson('/admin/collections', [
                'slug' => 'Reapers Talon',
                'name' => 'X',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_admin_atualiza_colecao(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $collection = Collection::create([
            'slug' => 'reapers-talon',
            'name' => 'Old',
            'hp_bonus' => 100,
        ]);

        $this->actingAs($admin)
            ->patchJson("/admin/collections/{$collection->id}", [
                'slug' => 'reapers-talon',
                'name' => 'New Name',
                'hp_bonus' => 2000,
                'attack_bonus' => 100,
                'defense_bonus' => 50,
            ])
            ->assertOk();

        $this->assertSame('New Name', $collection->fresh()->name);
        $this->assertSame(2000, $collection->fresh()->hp_bonus);
    }

    public function test_admin_remove_colecao(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $collection = Collection::create(['slug' => 'reapers-talon', 'name' => 'X']);

        $this->actingAs($admin)
            ->delete("/admin/collections/{$collection->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }
}
