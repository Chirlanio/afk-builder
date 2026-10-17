<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function makeHero(string $slug, string $tier = 'ascended', bool $active = true): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', $tier)->value('id'),
            'is_active' => $active,
            'pending_review' => false,
        ]);
    }

    public function test_guest_redireciona_pro_login(): void
    {
        $this->get('/roster/import')->assertRedirect('/login');
        $this->post('/roster/import', ['heroes' => []])->assertRedirect('/login');
    }

    public function test_index_lista_so_herois_ativos(): void
    {
        $user = User::factory()->create();
        $this->makeHero('lucius');
        $this->makeHero('inativo', active: false);

        $this->actingAs($user)
            ->get('/roster/import')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('roster/import')
                ->has('heroes', 1)
                ->where('heroes.0.name', 'Lucius'));
    }

    public function test_importa_herois_novos_no_roster(): void
    {
        $user = User::factory()->create();
        $lucius = $this->makeHero('lucius');
        $shemira = $this->makeHero('shemira');

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => [
                ['hero_id' => $lucius->id, 'level' => 180],
                ['hero_id' => $shemira->id, 'level' => 200],
            ]])
            ->assertRedirect(route('heroes.index'))
            ->assertSessionHas('success');

        $accountId = $user->defaultGameAccount->id;
        $this->assertDatabaseHas('user_heroes', ['game_account_id' => $accountId, 'hero_id' => $lucius->id, 'level' => 180]);
        $this->assertDatabaseHas('user_heroes', ['game_account_id' => $accountId, 'hero_id' => $shemira->id, 'level' => 200]);
    }

    public function test_heroi_existente_so_tem_o_nivel_atualizado(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 100,
            'signature_item' => 30,
            'furniture' => 9,
        ]);

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => [['hero_id' => $hero->id, 'level' => 220]]])
            ->assertRedirect();

        $userHero = UserHero::where('hero_id', $hero->id)->sole();
        $this->assertSame(220, $userHero->level);
        $this->assertSame(30, $userHero->signature_item);
        $this->assertSame(9, $userHero->furniture);
    }

    public function test_nivel_e_limitado_ao_max_level_do_tier(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('comum', tier: 'common');

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => [['hero_id' => $hero->id, 'level' => 200]]])
            ->assertRedirect();

        $this->assertSame(100, UserHero::where('hero_id', $hero->id)->value('level'));
    }

    public function test_rejeita_heroi_inativo_duplicado_ou_nivel_invalido(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $inativo = $this->makeHero('inativo', active: false);

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => [['hero_id' => $inativo->id, 'level' => 10]]])
            ->assertSessionHasErrors('heroes.0.hero_id');

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => [
                ['hero_id' => $hero->id, 'level' => 10],
                ['hero_id' => $hero->id, 'level' => 20],
            ]])
            ->assertSessionHasErrors('heroes.0.hero_id');

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => [['hero_id' => $hero->id, 'level' => 999]]])
            ->assertSessionHasErrors('heroes.0.level');

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => []])
            ->assertSessionHasErrors('heroes');

        $this->assertSame(0, UserHero::count());
    }

    public function test_importa_na_conta_de_jogo_ativa(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('lucius');
        $segunda = $user->gameAccounts()->create(['name' => 'Alt', 'is_default' => false]);

        $this->actingAs($user)
            ->post("/game-accounts/{$segunda->id}/switch")
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/roster/import', ['heroes' => [['hero_id' => $hero->id, 'level' => 50]]])
            ->assertRedirect();

        $this->assertDatabaseHas('user_heroes', ['game_account_id' => $segunda->id, 'hero_id' => $hero->id]);
        $this->assertDatabaseMissing('user_heroes', ['game_account_id' => $user->defaultGameAccount->id]);
    }
}
