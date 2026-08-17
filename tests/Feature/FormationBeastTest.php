<?php

namespace Tests\Feature;

use App\Models\Beast;
use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationBeastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    private function makeFormation(User $user, int $teamsCount = 1): Formation
    {
        $challenge = ChallengeType::where('code', 'campaign')->firstOrFail();
        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challenge->id,
            'name' => 'Test Formation',
            'mode' => 'real',
            'teams_count' => $teamsCount,
        ]);

        for ($pos = 1; $pos <= $teamsCount; $pos++) {
            $formation->teams()->create(['position' => $pos]);
        }

        return $formation;
    }

    private function makeHero(string $slug, ?string $type = null): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'type' => $type,
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_edit_carrega_lista_de_beasts_disponiveis(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        Beast::create(['slug' => 'dreary-ball', 'name' => 'Dreary Ball', 'is_active' => true]);
        Beast::create(['slug' => 'inativo', 'name' => 'Inativo', 'is_active' => false]);

        $this->actingAs($user)
            ->get("/formations/{$formation->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('beasts', 1)
                ->where('beasts.0.slug', 'dreary-ball')
            );
    }

    public function test_user_pode_atribuir_beast_a_team(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $beast = Beast::create(['slug' => 'dreary-ball', 'name' => 'Dreary Ball', 'is_active' => true]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'teams' => [
                    ['position' => 1, 'name' => null, 'beast_id' => $beast->id, 'heroes' => []],
                ],
            ])
            ->assertRedirect();

        $team = $formation->teams()->where('position', 1)->first();
        $this->assertSame($beast->id, $team->beast_id);
    }

    public function test_user_pode_remover_beast_setando_null(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $beast = Beast::create(['slug' => 'dreary-ball', 'name' => 'Dreary Ball']);
        $formation->teams()->first()->update(['beast_id' => $beast->id]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'teams' => [
                    ['position' => 1, 'name' => null, 'beast_id' => null, 'heroes' => []],
                ],
            ])
            ->assertRedirect();

        $this->assertNull($formation->teams()->first()->beast_id);
    }

    public function test_validacao_rejeita_beast_inexistente(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'teams' => [
                    ['position' => 1, 'name' => null, 'beast_id' => 9999, 'heroes' => []],
                ],
            ])
            ->assertSessionHasErrors('teams.0.beast_id');
    }

    public function test_validacao_rejeita_beast_inativo(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $inactive = Beast::create(['slug' => 'inativo', 'name' => 'Inativo', 'is_active' => false]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'teams' => [
                    ['position' => 1, 'name' => null, 'beast_id' => $inactive->id, 'heroes' => []],
                ],
            ])
            ->assertSessionHasErrors('teams.0.beast_id');
    }

    public function test_beasts_diferentes_por_team(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user, teamsCount: 2);
        $beastA = Beast::create(['slug' => 'beast-a', 'name' => 'A', 'is_active' => true]);
        $beastB = Beast::create(['slug' => 'beast-b', 'name' => 'B', 'is_active' => true]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'teams' => [
                    ['position' => 1, 'name' => null, 'beast_id' => $beastA->id, 'heroes' => []],
                    ['position' => 2, 'name' => null, 'beast_id' => $beastB->id, 'heroes' => []],
                ],
            ])
            ->assertRedirect();

        $teams = $formation->teams()->orderBy('position')->get();
        $this->assertSame($beastA->id, $teams[0]->beast_id);
        $this->assertSame($beastB->id, $teams[1]->beast_id);
    }

    public function test_best_beast_endpoint_seleciona_maior_buff_agregado(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        // Team com 3 heroes strength + 1 intelligence
        $heroes = [
            $this->makeHero('h1', 'strength'),
            $this->makeHero('h2', 'strength'),
            $this->makeHero('h3', 'strength'),
            $this->makeHero('h4', 'intelligence'),
        ];

        $team = $formation->teams()->first();
        foreach ($heroes as $idx => $hero) {
            $team->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => $idx + 1]);
        }

        // Beast A: 10% pra strength → 3×10 = 30
        $beastStr = Beast::create(['slug' => 'b-str', 'name' => 'Strength Beast', 'buffs_strength' => ['attack' => 10], 'is_active' => true]);
        // Beast B: 20% pra intelligence → 1×20 = 20
        $beastInt = Beast::create(['slug' => 'b-int', 'name' => 'Int Beast', 'buffs_intelligence' => ['attack' => 20], 'is_active' => true]);
        // Beast C: 50% pra agility (mas time não tem agi) → 0
        $beastAgi = Beast::create(['slug' => 'b-agi', 'name' => 'Agi Beast', 'buffs_agility' => ['attack' => 50], 'is_active' => true]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/{$team->id}/best-beast")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('beast.id', $beastStr->id);

        $this->assertSame($beastStr->id, $team->fresh()->beast_id);
    }

    public function test_best_beast_team_scope_aplica_a_todos(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        // Time misto sem dominância clara de type
        $heroes = [
            $this->makeHero('a', 'strength'),
            $this->makeHero('b', 'intelligence'),
            $this->makeHero('c', 'agility'),
        ];
        $team = $formation->teams()->first();
        foreach ($heroes as $idx => $hero) {
            $team->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => $idx + 1]);
        }

        // Beast com buff só pra agility → 1×30 = 30
        Beast::create(['slug' => 'agi-only', 'name' => 'Agi', 'buffs_agility' => ['attack' => 30], 'is_active' => true]);
        // Beast com buffs de team (aplica a TODOS) → 3×20 = 60 — deve vencer
        $teamBeast = Beast::create(['slug' => 'team-buff', 'name' => 'Team', 'buffs_team' => ['attack' => 20], 'is_active' => true]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/{$team->id}/best-beast")
            ->assertOk()
            ->assertJsonPath('beast.id', $teamBeast->id);
    }

    public function test_best_beast_rejeita_team_vazio(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        Beast::create(['slug' => 'b', 'name' => 'B', 'buffs_strength' => ['attack' => 10], 'is_active' => true]);

        $team = $formation->teams()->first();
        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/{$team->id}/best-beast")
            ->assertStatus(422)
            ->assertJsonPath('reason', 'team_empty');
    }

    public function test_best_beast_aplica_team_scope_mesmo_sem_heroes_tipados(): void
    {
        // Mudança vs Fase 48: heroes sem type ainda recebem buffs_team — não rejeitamos mais.
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $hero = $this->makeHero('typeless', null);
        $team = $formation->teams()->first();
        $team->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => 1]);

        $beast = Beast::create(['slug' => 'team-b', 'name' => 'Team B', 'buffs_team' => ['attack' => 15], 'is_active' => true]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/{$team->id}/best-beast")
            ->assertOk()
            ->assertJsonPath('beast.id', $beast->id);
    }

    public function test_best_beast_rejeita_quando_bestas_sem_buff_configurado(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $hero = $this->makeHero('h1', 'strength');
        $team = $formation->teams()->first();
        $team->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => 1]);

        // Bestas sem nenhum buff em nenhum scope
        Beast::create(['slug' => 'b1', 'name' => 'B1', 'is_active' => true]);
        Beast::create(['slug' => 'b2', 'name' => 'B2', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/teams/{$team->id}/best-beast")
            ->assertStatus(422)
            ->assertJsonPath('reason', 'beasts_without_buffs');
    }

    public function test_best_beast_403_se_team_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $formation = $this->makeFormation($owner);
        $team = $formation->teams()->first();

        $this->actingAs($intruder)
            ->postJson("/formations/{$formation->id}/teams/{$team->id}/best-beast")
            ->assertForbidden();
    }

    public function test_best_beast_404_se_team_nao_pertence_a_formation(): void
    {
        $user = User::factory()->create();
        $formationA = $this->makeFormation($user);
        $formationB = $this->makeFormation($user);
        $teamOther = $formationB->teams()->first();

        $this->actingAs($user)
            ->postJson("/formations/{$formationA->id}/teams/{$teamOther->id}/best-beast")
            ->assertNotFound();
    }
}
