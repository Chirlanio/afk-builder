<?php

namespace Tests\Feature;

use App\Models\Guide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    private function makeGuide(array $overrides = []): Guide
    {
        return Guide::create(array_merge([
            'slug' => 'guia-teste',
            'title' => 'Guia de Teste',
            'category' => Guide::CATEGORY_MECANICAS,
            'summary' => 'Resumo curto.',
            'content' => 'Conteúdo completo do guia.',
            'source_url' => 'https://example.com',
            'display_order' => 0,
        ], $overrides));
    }

    public function test_guest_redireciona_para_login(): void
    {
        $this->get('/guides')->assertRedirect('/login');
    }

    public function test_index_lista_guias_agrupados_por_categoria(): void
    {
        $user = User::factory()->create();
        $this->makeGuide(['slug' => 'mec-1', 'title' => 'Mec 1', 'category' => Guide::CATEGORY_MECANICAS]);
        $this->makeGuide(['slug' => 'mod-1', 'title' => 'Modo 1', 'category' => Guide::CATEGORY_MODOS]);
        $this->makeGuide(['slug' => 'mec-2', 'title' => 'Mec 2', 'category' => Guide::CATEGORY_MECANICAS]);

        $this->actingAs($user)
            ->get('/guides')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('guides/index')
                ->has('categories', 3)
                ->where('categories.0.code', 'mecanicas')
                ->where('categories.0.label', 'Mecânicas')
                ->has('categories.0.guides', 2)
                ->where('categories.1.code', 'modos')
                ->has('categories.1.guides', 1)
            );
    }

    public function test_show_renderiza_o_guia(): void
    {
        $user = User::factory()->create();
        $guide = $this->makeGuide(['slug' => 'critico', 'title' => 'Crítico Ressonante']);

        $this->actingAs($user)
            ->get("/guides/{$guide->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('guides/show')
                ->where('guide.id', $guide->id)
                ->where('guide.slug', 'critico')
                ->where('guide.title', 'Crítico Ressonante')
                ->where('categoryLabel', 'Mecânicas')
            );
    }

    public function test_show_404_quando_slug_inexistente(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/guides/inexistente')
            ->assertNotFound();
    }
}
