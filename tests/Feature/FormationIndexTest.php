<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Formation;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormationIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            ChallengeTypeSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }

    private function makeFormation(User $user, string $challengeCode, array $overrides = []): Formation
    {
        $challengeType = ChallengeType::where('code', $challengeCode)->first();
        $formation = $user->formations()->create(array_merge([
            'challenge_type_id' => $challengeType->id,
            'name' => 'F-'.uniqid(),
            'mode' => 'target',
            'teams_count' => 1,
        ], $overrides));
        $formation->teams()->create(['position' => 1]);

        return $formation;
    }

    public function test_index_retorna_campos_necessarios_para_filtros(): void
    {
        $user = User::factory()->create();
        $this->makeFormation($user, 'campaign', [
            'name' => 'Com tudo',
            'notes' => 'meu plano aqui',
            'share_token' => Str::random(32),
            'beast_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/formations')->assertOk();
        $formations = $response->viewData('page')['props']['formations'];

        $this->assertCount(1, $formations);
        $f = $formations[0];

        // Campos esperados pra filtragem client-side
        $this->assertSame('Com tudo', $f['name']);
        $this->assertNotNull($f['share_token']);
        $this->assertNull($f['beast_id']);
        $this->assertTrue($f['has_notes']);
        // O conteúdo real das notas não deve ir no payload (privacidade + tamanho)
        $this->assertArrayNotHasKey('notes', $f);
    }

    public function test_has_notes_e_false_quando_notas_estao_vazias_ou_whitespace(): void
    {
        $user = User::factory()->create();
        $this->makeFormation($user, 'campaign', ['name' => 'Vazia', 'notes' => null]);
        $this->makeFormation($user, 'campaign', ['name' => 'Whitespace', 'notes' => "   \n\t  "]);
        $this->makeFormation($user, 'campaign', ['name' => 'Real', 'notes' => 'algo']);

        $response = $this->actingAs($user)->get('/formations')->assertOk();
        $formations = collect($response->viewData('page')['props']['formations'])->keyBy('name');

        $this->assertFalse($formations['Vazia']['has_notes']);
        $this->assertFalse($formations['Whitespace']['has_notes']);
        $this->assertTrue($formations['Real']['has_notes']);
    }

    public function test_available_challenge_types_inclui_apenas_os_com_formacoes(): void
    {
        $user = User::factory()->create();
        $this->makeFormation($user, 'campaign');
        $this->makeFormation($user, 'arena_of_heroes');

        $response = $this->actingAs($user)->get('/formations')->assertOk();
        $cts = collect($response->viewData('page')['props']['availableChallengeTypes']);

        $codes = $cts->pluck('code')->all();
        $this->assertCount(2, $cts);
        $this->assertContains('campaign', $codes);
        $this->assertContains('arena_of_heroes', $codes);
        $this->assertNotContains('dream_realm', $codes); // sem formação
    }

    public function test_available_challenge_types_deduplica(): void
    {
        $user = User::factory()->create();
        // 3 formações do mesmo challenge type
        $this->makeFormation($user, 'campaign');
        $this->makeFormation($user, 'campaign');
        $this->makeFormation($user, 'campaign');

        $response = $this->actingAs($user)->get('/formations')->assertOk();
        $cts = $response->viewData('page')['props']['availableChallengeTypes'];

        $this->assertCount(1, $cts);
    }

    public function test_outro_user_nao_ve_formacoes(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->makeFormation($userA, 'campaign');

        $response = $this->actingAs($userB)->get('/formations')->assertOk();
        $formations = $response->viewData('page')['props']['formations'];
        $cts = $response->viewData('page')['props']['availableChallengeTypes'];

        $this->assertCount(0, $formations);
        $this->assertCount(0, $cts);
    }
}
