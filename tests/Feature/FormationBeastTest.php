<?php

namespace Tests\Feature;

use App\Models\Beast;
use App\Models\ChallengeType;
use App\Models\Formation;
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

    private function makeFormation(User $user): Formation
    {
        $challenge = ChallengeType::where('code', 'campaign')->firstOrFail();
        $formation = $user->formations()->create([
            'challenge_type_id' => $challenge->id,
            'name' => 'Test Formation',
            'mode' => 'real',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        return $formation;
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

    public function test_user_pode_atribuir_beast_a_formacao(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $beast = Beast::create(['slug' => 'dreary-ball', 'name' => 'Dreary Ball', 'is_active' => true]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'beast_id' => $beast->id,
                'teams' => [
                    ['position' => 1, 'name' => null, 'heroes' => []],
                ],
            ])
            ->assertRedirect();

        $this->assertSame($beast->id, $formation->fresh()->beast_id);
    }

    public function test_user_pode_remover_beast_setando_null(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $beast = Beast::create(['slug' => 'dreary-ball', 'name' => 'Dreary Ball']);
        $formation->update(['beast_id' => $beast->id]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'beast_id' => null,
                'teams' => [
                    ['position' => 1, 'name' => null, 'heroes' => []],
                ],
            ])
            ->assertRedirect();

        $this->assertNull($formation->fresh()->beast_id);
    }

    public function test_validacao_rejeita_beast_inexistente(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'beast_id' => 9999,
                'teams' => [
                    ['position' => 1, 'name' => null, 'heroes' => []],
                ],
            ])
            ->assertSessionHasErrors('beast_id');
    }

    public function test_validacao_rejeita_beast_inativo(): void
    {
        $user = User::factory()->create();
        $formation = $this->makeFormation($user);
        $inactive = Beast::create(['slug' => 'inativo', 'name' => 'Inativo', 'is_active' => false]);

        $this->actingAs($user)
            ->patch("/formations/{$formation->id}", [
                'name' => 'Test Formation',
                'beast_id' => $inactive->id,
                'teams' => [
                    ['position' => 1, 'name' => null, 'heroes' => []],
                ],
            ])
            ->assertSessionHasErrors('beast_id');
    }
}
