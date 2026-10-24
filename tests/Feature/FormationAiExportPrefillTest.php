<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationAiExportPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    private function aiExportPropFor(string $challengeCode, string $planning, int $teams = 1): mixed
    {
        $user = User::factory()->create();
        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => ChallengeType::where('code', $challengeCode)->value('id'),
            'name' => 'F',
            'mode' => $planning,
            'teams_count' => $teams,
        ]);
        foreach (range(1, $teams) as $position) {
            $formation->teams()->create(['position' => $position]);
        }

        $prop = null;
        $this->actingAs($user)->get("/formations/{$formation->id}/edit")
            ->assertOk()
            ->assertInertia(function ($page) use (&$prop) {
                $page->component('formations/edit');
                // Compara o array inteiro: uma chave a mais também é erro.
                $prop = $page->toArray()['props']['aiExport'];
            });

        return $prop;
    }

    public function test_formation_hands_its_objective_planning_and_teams_to_the_ai_export(): void
    {
        $this->assertSame(
            ['objective' => 'campaign', 'planning' => 'target', 'params' => ['teams' => '3']],
            $this->aiExportPropFor('campaign', 'target', 3),
        );
        $this->assertSame(
            ['objective' => 'arena_of_heroes', 'planning' => 'real', 'params' => []],
            $this->aiExportPropFor('arena_of_heroes', 'real'),
        );
    }

    public function test_every_challenge_type_with_an_objective_maps_to_it(): void
    {
        foreach (['dragon_trial' => [2, 'target'], 'kings_tower' => [1, 'real'], 'dream_realm' => [1, 'target']] as $code => [$teams, $planning]) {
            $this->assertSame(
                ['objective' => $code, 'planning' => $planning, 'params' => []],
                $this->aiExportPropFor($code, $planning, $teams),
            );
        }
    }

    public function test_challenge_type_without_objective_opens_the_export_blank(): void
    {
        $this->assertSame(
            ['objective' => null, 'planning' => 'real', 'params' => []],
            $this->aiExportPropFor('bountiful_trials', 'real'),
        );
    }
}
