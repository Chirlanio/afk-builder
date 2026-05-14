<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\RosterPowerSnapshot;
use App\Models\Tier;
use App\Models\User;
use App\Models\UserHero;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RosterPowerSnapshotTest extends TestCase
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

    private function makeHero(string $slug = 'h'): Hero
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

    private function makeUserHero(User $user, Hero $hero): UserHero
    {
        return UserHero::create([
            'user_id' => $user->id,
            'hero_id' => $hero->id,
            'level' => 240,
            'ascension_value' => 16,
            'ascension_code' => 'ascended_5_stars',
            'signature_item' => 40,
            'furniture' => 9,
            'engraving' => 80,
        ]);
    }

    public function test_comando_cria_snapshot_para_user_com_roster(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero('thoran');
        $this->makeUserHero($user, $hero);

        $this->artisan('roster:snapshot')->assertSuccessful();

        $snapshot = $user->rosterPowerSnapshots()->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(1, $snapshot->hero_count);
        $this->assertGreaterThan(0, $snapshot->total_power);
        $this->assertSame($hero->id, $snapshot->top_hero_id);
    }

    public function test_comando_skipa_user_sem_roster(): void
    {
        User::factory()->create();

        $this->artisan('roster:snapshot')->assertSuccessful();

        $this->assertSame(0, RosterPowerSnapshot::count());
    }

    public function test_comando_e_idempotente_por_dia(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $this->makeUserHero($user, $hero);

        $this->artisan('roster:snapshot')->assertSuccessful();
        $this->artisan('roster:snapshot')->assertSuccessful();
        $this->artisan('roster:snapshot')->assertSuccessful();

        $this->assertSame(1, $user->rosterPowerSnapshots()->count());
    }

    public function test_comando_atualiza_snapshot_quando_roster_muda(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $userHero = $this->makeUserHero($user, $hero);

        $this->artisan('roster:snapshot')->assertSuccessful();
        $firstPower = $user->rosterPowerSnapshots()->first()->total_power;

        // Reduz investimento → power cai
        $userHero->update(['signature_item' => 0, 'furniture' => 0, 'engraving' => 0]);
        $this->artisan('roster:snapshot')->assertSuccessful();

        $this->assertSame(1, $user->rosterPowerSnapshots()->count());
        $secondPower = $user->rosterPowerSnapshots()->first()->total_power;
        $this->assertLessThan($firstPower, $secondPower);
    }

    public function test_comando_aceita_user_id_option(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $hero = $this->makeHero();
        $this->makeUserHero($userA, $hero);
        $this->makeUserHero($userB, $hero);

        $this->artisan("roster:snapshot --user-id={$userA->id}")->assertSuccessful();

        $this->assertSame(1, $userA->rosterPowerSnapshots()->count());
        $this->assertSame(0, $userB->rosterPowerSnapshots()->count());
    }

    public function test_comando_aceita_date_option(): void
    {
        $user = User::factory()->create();
        $hero = $this->makeHero();
        $this->makeUserHero($user, $hero);

        $this->artisan('roster:snapshot --date=2026-01-15')->assertSuccessful();

        $snapshot = $user->rosterPowerSnapshots()->first();
        $this->assertSame('2026-01-15', $snapshot->snapshot_date->toDateString());
    }

    public function test_dashboard_inclui_power_history(): void
    {
        $user = User::factory()->create();
        // Cria 3 snapshots em dias diferentes
        for ($i = 0; $i < 3; $i++) {
            RosterPowerSnapshot::create([
                'user_id' => $user->id,
                'snapshot_date' => Carbon::today()->subDays(2 - $i)->toDateString(),
                'total_power' => 10_000 + $i * 1_000,
                'hero_count' => 5,
                'top_hero_id' => null,
                'top_hero_power' => null,
                'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $history = $response->viewData('page')['props']['powerHistory'];

        $this->assertCount(3, $history);
        // Ordem crescente por data (chart visualiza esquerda → direita)
        $this->assertSame(10_000, $history[0]['total_power']);
        $this->assertSame(12_000, $history[2]['total_power']);
    }

    public function test_dashboard_limite_30_snapshots(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 40; $i++) {
            RosterPowerSnapshot::create([
                'user_id' => $user->id,
                'snapshot_date' => Carbon::today()->subDays($i)->toDateString(),
                'total_power' => 10_000,
                'hero_count' => 5,
                'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $history = $response->viewData('page')['props']['powerHistory'];

        $this->assertCount(30, $history);
    }

    public function test_dashboard_isola_history_por_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        RosterPowerSnapshot::create([
            'user_id' => $userA->id,
            'snapshot_date' => Carbon::today()->toDateString(),
            'total_power' => 10_000,
            'hero_count' => 5,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($userB)->get('/dashboard')->assertOk();
        $history = $response->viewData('page')['props']['powerHistory'];

        $this->assertCount(0, $history);
    }

    public function test_cascade_delete_quando_user_deletado(): void
    {
        $user = User::factory()->create();
        RosterPowerSnapshot::create([
            'user_id' => $user->id,
            'snapshot_date' => Carbon::today()->toDateString(),
            'total_power' => 10_000,
            'hero_count' => 5,
            'created_at' => now(),
        ]);

        $user->delete();

        $this->assertSame(0, RosterPowerSnapshot::count());
    }
}
