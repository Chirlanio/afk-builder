<?php

namespace Tests\Feature;

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

class FormationTeamsCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    /**
     * Garante que existe um challenge_type com range de teams (min!=max) pra testar.
     * Se nenhum do seeder tem range, força um update num existente.
     */
    private function rangeChallengeType(): ChallengeType
    {
        // Usa arcane_labyrinth do seeder (min=1, max=5) — range amplo, ideal pra cobrir
        // 2→4 (aumenta), 4→2 (diminui), e validações fora dos limites.
        $ct = ChallengeType::where('code', 'arcane_labyrinth')->first();
        if ($ct === null) {
            $ct = ChallengeType::first();
            $ct->update(['min_teams' => 1, 'max_teams' => 5]);
        }

        return $ct;
    }

    private function makeFormation(User $user, ChallengeType $ct, int $teamsCount): Formation
    {
        $formation = $user->formations()->create([
            'challenge_type_id' => $ct->id,
            'name' => 'Test',
            'mode' => 'real',
            'teams_count' => $teamsCount,
        ]);
        for ($p = 1; $p <= $teamsCount; $p++) {
            $formation->teams()->create(['position' => $p]);
        }

        return $formation;
    }

    private function makeHero(): Hero
    {
        return Hero::create([
            'slug' => 'h-'.uniqid(),
            'name' => 'Hero',
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    public function test_create_aceita_teams_count_dentro_do_range(): void
    {
        $user = User::factory()->create();
        $ct = $this->rangeChallengeType();

        $this->actingAs($user)
            ->post('/formations', [
                'name' => 'Test',
                'challenge_type_id' => $ct->id,
                'mode' => 'real',
                'teams_count' => 3,
            ])
            ->assertRedirect();

        $formation = $user->formations()->first();
        $this->assertSame(3, $formation->teams_count);
        $this->assertSame(3, $formation->teams()->count());
    }

    public function test_update_teams_count_aumenta_cria_teams_vazios(): void
    {
        $user = User::factory()->create();
        $ct = $this->rangeChallengeType();
        $formation = $this->makeFormation($user, $ct, 2);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}/teams-count", ['teams_count' => 4])
            ->assertRedirect();

        $formation->refresh();
        $this->assertSame(4, $formation->teams_count);
        $this->assertSame(4, $formation->teams()->count());
        $positions = $formation->teams()->pluck('position')->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4], $positions);
    }

    public function test_update_teams_count_diminui_apaga_teams_excedentes(): void
    {
        $user = User::factory()->create();
        $ct = $this->rangeChallengeType();
        $formation = $this->makeFormation($user, $ct, 4);

        // Adiciona hero no team que vai sumir (position 4) pra verificar cascade
        $hero = $this->makeHero();
        $team4 = $formation->teams()->where('position', 4)->first();
        $team4->teamHeroes()->create(['hero_id' => $hero->id, 'slot' => 1]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}/teams-count", ['teams_count' => 2])
            ->assertRedirect();

        $formation->refresh();
        $this->assertSame(2, $formation->teams_count);
        $this->assertSame(2, $formation->teams()->count());
        $positions = $formation->teams()->pluck('position')->sort()->values()->all();
        $this->assertSame([1, 2], $positions);
        // Cascade: team_heroes do team apagado também somem
        $this->assertSame(0, \App\Models\FormationTeamHero::where('hero_id', $hero->id)->count());
    }

    public function test_update_teams_count_no_op_quando_igual(): void
    {
        $user = User::factory()->create();
        $ct = $this->rangeChallengeType();
        $formation = $this->makeFormation($user, $ct, 3);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}/teams-count", ['teams_count' => 3])
            ->assertRedirect();

        $this->assertSame(3, $formation->fresh()->teams_count);
        $this->assertSame(3, $formation->teams()->count());
    }

    public function test_update_teams_count_rejeita_acima_do_max(): void
    {
        $user = User::factory()->create();
        $ct = $this->rangeChallengeType();
        $formation = $this->makeFormation($user, $ct, 2);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}/teams-count", ['teams_count' => $ct->max_teams + 1])
            ->assertSessionHasErrors('teams_count');

        $this->assertSame(2, $formation->fresh()->teams_count);
    }

    public function test_update_teams_count_rejeita_abaixo_do_min(): void
    {
        $user = User::factory()->create();
        $ct = $this->rangeChallengeType();
        $formation = $this->makeFormation($user, $ct, 2);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}/teams-count", ['teams_count' => $ct->min_teams - 1])
            ->assertSessionHasErrors('teams_count');
    }

    public function test_update_teams_count_403_de_outro_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $ct = $this->rangeChallengeType();
        $formation = $this->makeFormation($owner, $ct, 2);

        $this->actingAs($intruder)
            ->patch("/formations/{$formation->id}/teams-count", ['teams_count' => 3])
            ->assertForbidden();
    }

    public function test_update_teams_count_gera_snapshot_de_versao(): void
    {
        $user = User::factory()->create();
        $ct = $this->rangeChallengeType();
        $formation = $this->makeFormation($user, $ct, 2);

        $this->assertSame(0, $formation->versions()->count());

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}/teams-count", ['teams_count' => 4])
            ->assertRedirect();

        // Snapshot da versão anterior fica preservado (mesma lógica que update)
        $this->assertSame(1, $formation->versions()->count());
    }
}
