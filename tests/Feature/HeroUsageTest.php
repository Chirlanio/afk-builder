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
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroUsageTest extends TestCase
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

    private function makeFormationWithHeroes(User $user, string $challengeCode, array $heroIds, int $teamsCount = 1): Formation
    {
        $challenge = ChallengeType::where('code', $challengeCode)->first();
        $formation = $user->defaultGameAccount->formations()->create([
            'challenge_type_id' => $challenge->id,
            'name' => "F-{$challengeCode}-".uniqid(),
            'mode' => 'target',
            'teams_count' => $teamsCount,
        ]);
        $team = $formation->teams()->create(['position' => 1]);
        foreach ($heroIds as $idx => $heroId) {
            $team->teamHeroes()->create([
                'hero_id' => $heroId,
                'slot' => $idx + 1,
            ]);
        }

        return $formation;
    }

    public function test_guest_redirecionado_para_login(): void
    {
        $this->get('/heroes/usage')->assertRedirect('/login');
    }

    public function test_autenticado_acessa_pagina(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/heroes/usage')->assertOk();
    }

    public function test_top_used_conta_corretamente(): void
    {
        $user = User::factory()->create();
        $heroA = $this->makeHero('hero-a');
        $heroB = $this->makeHero('hero-b');
        $heroC = $this->makeHero('hero-c');

        // heroA em 2 formações; heroB em 1; heroC nunca
        $this->makeFormationWithHeroes($user, 'campaign', [$heroA->id, $heroB->id]);
        $this->makeFormationWithHeroes($user, 'arena_of_heroes', [$heroA->id]);

        $response = $this->actingAs($user)->get('/heroes/usage')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(2, $props['stats']['total_used']);

        $topUsed = collect($props['topUsed']);
        $this->assertCount(2, $topUsed);
        $first = $topUsed->first();
        $this->assertSame('hero-a', $first['slug']);
        $this->assertSame(2, $first['team_count']);
        $this->assertSame(2, $first['formation_count']);
        $this->assertSame(2, $first['challenge_types_count']);
    }

    public function test_never_used_lista_heroes_do_roster_fora_de_formacoes(): void
    {
        $user = User::factory()->create();
        $heroUsed = $this->makeHero('used');
        $heroIdle = $this->makeHero('idle');
        $heroIdle2 = $this->makeHero('idle2');

        $user->defaultGameAccount->userHeroes()->createMany([
            ['hero_id' => $heroUsed->id, 'level' => 200, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $heroIdle->id, 'level' => 200, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
            ['hero_id' => $heroIdle2->id, 'level' => 200, 'ascension_value' => 12, 'ascension_code' => 'ascended_1_star', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
        ]);

        $this->makeFormationWithHeroes($user, 'campaign', [$heroUsed->id]);

        $response = $this->actingAs($user)->get('/heroes/usage')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(2, $props['stats']['never_used_total']);
        $slugs = collect($props['neverUsed'])->pluck('slug')->all();
        $this->assertContains('idle', $slugs);
        $this->assertContains('idle2', $slugs);
        $this->assertNotContains('used', $slugs);
    }

    public function test_invested_forgotten_destaca_heroes_com_si_alto_sem_uso(): void
    {
        $user = User::factory()->create();
        $heroRich = $this->makeHero('rich');
        $heroBasic = $this->makeHero('basic');

        $user->defaultGameAccount->userHeroes()->createMany([
            ['hero_id' => $heroRich->id, 'level' => 240, 'ascension_value' => 14, 'ascension_code' => 'ascended_3_stars', 'signature_item' => 30, 'furniture' => 9, 'engraving' => 80],
            ['hero_id' => $heroBasic->id, 'level' => 100, 'ascension_value' => 5, 'ascension_code' => 'rare_plus', 'signature_item' => 0, 'furniture' => 0, 'engraving' => 0],
        ]);

        // Nenhum dos dois está em formação
        $response = $this->actingAs($user)->get('/heroes/usage')->assertOk();
        $props = $response->viewData('page')['props'];

        // Apenas o "rich" entra em invested_forgotten
        $this->assertSame(1, $props['stats']['invested_forgotten_total']);
        $invested = collect($props['investedForgotten']);
        $this->assertCount(1, $invested);
        $this->assertSame('rich', $invested->first()['slug']);
        $this->assertSame(30, $invested->first()['signature_item']);
    }

    public function test_hero_em_formacao_nao_aparece_em_invested_forgotten(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('used-rich');
        $user->defaultGameAccount->userHeroes()->create([
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 16,
            'ascension_code' => 'ascended_5_stars',
            'signature_item' => 40,
            'furniture' => 9,
            'engraving' => 80,
        ]);

        $this->makeFormationWithHeroes($user, 'campaign', [$hero->id]);

        $response = $this->actingAs($user)->get('/heroes/usage')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(0, $props['stats']['invested_forgotten_total']);
    }

    public function test_usage_by_challenge_type_agrupa_e_ordena_por_uso(): void
    {
        $user = User::factory()->create();
        $heroA = $this->makeHero('h-a');
        $heroB = $this->makeHero('h-b');

        // Campaign: heroA 2 vezes, heroB 1
        $this->makeFormationWithHeroes($user, 'campaign', [$heroA->id]);
        $this->makeFormationWithHeroes($user, 'campaign', [$heroA->id, $heroB->id]);
        // Arena: heroB 1
        $this->makeFormationWithHeroes($user, 'arena_of_heroes', [$heroB->id]);

        $response = $this->actingAs($user)->get('/heroes/usage')->assertOk();
        $props = $response->viewData('page')['props'];

        $usage = collect($props['usageByChallengeType']);
        $this->assertCount(2, $usage);

        $campaign = $usage->firstWhere('code', 'campaign');
        $this->assertSame('h-a', $campaign['heroes'][0]['slug']);
        $this->assertSame(2, $campaign['heroes'][0]['team_count']);
        $this->assertSame('h-b', $campaign['heroes'][1]['slug']);

        $arena = $usage->firstWhere('code', 'arena_of_heroes');
        $this->assertSame('h-b', $arena['heroes'][0]['slug']);
    }

    public function test_payload_isola_dados_por_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $hero = $this->makeHero('shared');

        $this->makeFormationWithHeroes($userA, 'campaign', [$hero->id]);

        $response = $this->actingAs($userB)->get('/heroes/usage')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(0, $props['stats']['total_used']);
        $this->assertCount(0, $props['topUsed']);
    }

    public function test_roster_vazio_retorna_estados_vazios(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/heroes/usage')->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(0, $props['stats']['total_used']);
        $this->assertSame(0, $props['stats']['total_in_roster']);
        $this->assertCount(0, $props['topUsed']);
        $this->assertCount(0, $props['neverUsed']);
        $this->assertCount(0, $props['investedForgotten']);
        $this->assertCount(0, $props['usageByChallengeType']);
    }
}
