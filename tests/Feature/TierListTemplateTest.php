<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserTierList;
use App\Models\UserTierListItem;
use App\Models\UserTierListRow;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierListTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeHero(string $slug): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    /**
     * Cria uma tier list com 2 rows e algumas itens, simulando o que store + placeHero criariam.
     */
    private function makePopulatedList(User $owner, bool $isTemplate = false): UserTierList
    {
        $list = $owner->tierLists()->create([
            'name' => 'Meta Q1',
            'description' => 'Tier list de PvP',
            'is_template' => $isTemplate,
        ]);

        $rowS = $list->rows()->create(['label' => 'S', 'color' => 'rose', 'position' => 0]);
        $rowA = $list->rows()->create(['label' => 'A', 'color' => 'amber', 'position' => 1]);

        $hero1 = $this->makeHero('thoran-'.$list->id);
        $hero2 = $this->makeHero('lucretia-'.$list->id);

        UserTierListItem::create(['user_tier_list_row_id' => $rowS->id, 'hero_id' => $hero1->id, 'position' => 0]);
        UserTierListItem::create(['user_tier_list_row_id' => $rowA->id, 'hero_id' => $hero2->id, 'position' => 0]);

        return $list;
    }

    public function test_admin_pode_marcar_lista_como_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = $this->makePopulatedList($admin);

        $this->actingAs($admin)
            ->patchJson("/tier-lists/{$list->id}/template", ['is_template' => true])
            ->assertOk()
            ->assertJsonPath('is_template', true);

        $this->assertTrue($list->fresh()->is_template);
    }

    public function test_user_normal_nao_pode_marcar_template(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $list = $this->makePopulatedList($user);

        $this->actingAs($user)
            ->patchJson("/tier-lists/{$list->id}/template", ['is_template' => true])
            ->assertForbidden();

        $this->assertFalse($list->fresh()->is_template);
    }

    public function test_admin_nao_pode_marcar_lista_de_outro_user_como_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => false]);
        $list = $this->makePopulatedList($other);

        $this->actingAs($admin)
            ->patchJson("/tier-lists/{$list->id}/template", ['is_template' => true])
            ->assertForbidden();
    }

    public function test_pagina_de_templates_lista_apenas_templates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $template = $this->makePopulatedList($admin, isTemplate: true);
        $nonTemplate = $this->makePopulatedList($admin, isTemplate: false);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/tier-lists/templates')->assertOk();
        $templates = $response->viewData('page')['props']['templates'];

        $this->assertCount(1, $templates);
        $this->assertSame($template->id, $templates[0]['id']);
        $this->assertNotEquals($nonTemplate->id, $templates[0]['id']);
    }

    public function test_clone_copia_rows_e_items_para_o_user_atual(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $template = $this->makePopulatedList($admin, isTemplate: true);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/tier-lists/templates/{$template->id}/clone")
            ->assertRedirect();

        $copy = $user->tierLists()->first();
        $this->assertNotNull($copy);
        $this->assertSame($user->id, $copy->user_id);
        $this->assertFalse($copy->is_template);
        $this->assertSame($template->id, $copy->cloned_from_id);

        // Rows copiadas com mesma label/color/position
        $this->assertCount(2, $copy->rows);
        $this->assertSame('S', $copy->rows[0]->label);
        $this->assertSame('rose', $copy->rows[0]->color);
        $this->assertSame('A', $copy->rows[1]->label);
        $this->assertSame('amber', $copy->rows[1]->color);

        // Items copiados com mesmos hero_ids/positions
        $copyRowS = $copy->rows[0];
        $copyRowS->load('items');
        $templateRowS = $template->rows[0];
        $templateRowS->load('items');
        $this->assertSame(
            $templateRowS->items->pluck('hero_id')->all(),
            $copyRowS->items->pluck('hero_id')->all(),
        );
    }

    public function test_clone_falha_se_lista_nao_for_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $list = $this->makePopulatedList($admin, isTemplate: false);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post("/tier-lists/templates/{$list->id}/clone")
            ->assertNotFound();

        $this->assertSame(0, $user->tierLists()->count());
    }

    public function test_clone_exige_autenticacao(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $template = $this->makePopulatedList($admin, isTemplate: true);

        $this->post("/tier-lists/templates/{$template->id}/clone")
            ->assertRedirect('/login');
    }

    public function test_pagina_de_templates_exige_autenticacao(): void
    {
        $this->get('/tier-lists/templates')->assertRedirect('/login');
    }

    public function test_clone_nao_propaga_flag_de_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $template = $this->makePopulatedList($admin, isTemplate: true);

        $user = User::factory()->create();
        $this->actingAs($user)->post("/tier-lists/templates/{$template->id}/clone");

        $copy = $user->tierLists()->first();
        $this->assertFalse($copy->is_template);
    }

    public function test_dois_clones_geram_listas_independentes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $template = $this->makePopulatedList($admin, isTemplate: true);

        $user = User::factory()->create();
        $this->actingAs($user)->post("/tier-lists/templates/{$template->id}/clone");
        $this->actingAs($user)->post("/tier-lists/templates/{$template->id}/clone");

        $this->assertSame(2, $user->tierLists()->count());
        $copies = $user->tierLists()->get();
        $this->assertNotSame($copies[0]->id, $copies[1]->id);
    }

    public function test_delete_template_seta_cloned_from_id_null_no_copy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $template = $this->makePopulatedList($admin, isTemplate: true);

        $user = User::factory()->create();
        $this->actingAs($user)->post("/tier-lists/templates/{$template->id}/clone");
        $copy = $user->tierLists()->first();
        $this->assertSame($template->id, $copy->cloned_from_id);

        $template->delete();

        $this->assertNull($copy->fresh()->cloned_from_id);
        // Cópia sobrevive ao delete do template
        $this->assertTrue($user->tierLists()->whereKey($copy->id)->exists());
    }

    public function test_index_inclui_flag_is_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->makePopulatedList($admin, isTemplate: true);

        $response = $this->actingAs($admin)->get('/tier-lists')->assertOk();
        $lists = $response->viewData('page')['props']['tierLists'];

        $this->assertTrue((bool) $lists[0]['is_template']);
    }
}
