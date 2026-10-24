<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroCounter;
use App\Models\HeroRating;
use App\Models\Tier;
use App\Models\User;
use App\Services\ActiveGameAccount;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CountersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
        $this->user = User::factory()->create();
    }

    private function makeHero(string $name, ?string $slug = null): Hero
    {
        return Hero::create([
            'slug' => $slug ?? Str::slug($name),
            'name' => $name,
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'mage')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    private function own(Hero ...$heroes): void
    {
        foreach ($heroes as $hero) {
            $this->user->defaultGameAccount->userHeroes()->create(['hero_id' => $hero->id, 'level' => 240]);
        }
    }

    private function counter(Hero $hero, Hero $target, int $strength = 1, string $context = 'pvp'): void
    {
        HeroCounter::create([
            'hero_id' => $hero->id, 'target_hero_id' => $target->id, 'context' => $context,
            'strength' => $strength, 'reason' => "{$hero->name} vence {$target->name}", 'source_url' => 'https://afk.guide/?p=2337',
        ]);
    }

    private function rate(Hero $hero, string $tier, string $context = 'pvp'): void
    {
        HeroRating::create(['hero_id' => $hero->id, 'mode' => $context, 'stage' => 'end', 'tier' => $tier, 'source' => 'afk.guide']);
    }

    /**
     * @param  array<int, Hero>  $enemy  posição => herói
     */
    private function visit(array $enemy, ?User $as = null): TestResponse
    {
        $query = http_build_query(['enemy' => array_map(fn (Hero $hero) => $hero->id, $enemy)]);

        return $this->actingAs($as ?? $this->user)->get('/counters?'.$query);
    }

    private function props(TestResponse $response): array
    {
        return $response->assertOk()->viewData('page')['props'];
    }

    public function test_page_opens_empty_without_an_enemy_team(): void
    {
        $props = $this->props($this->actingAs($this->user)->get('/counters'));

        $this->assertSame([], $props['enemy']);
        $this->assertSame([], $props['ranking']);
    }

    public function test_ranking_orders_by_total_strength_and_breaks_ties_by_pvp_tier(): void
    {
        [$lucius, $brutus] = [$this->makeHero('Lucius'), $this->makeHero('Brutus')];
        [$strong, $double, $tieA, $tieB] = [$this->makeHero('Strong'), $this->makeHero('Double'), $this->makeHero('TieA'), $this->makeHero('TieB')];
        $this->own($strong, $double, $tieA, $tieB);
        $this->counter($strong, $lucius, 2);
        $this->counter($strong, $brutus, 2);              // 4
        $this->counter($double, $lucius, 2);              // 2
        $this->counter($tieA, $lucius, 1);                // 1, tier B
        $this->counter($tieB, $brutus, 1);                // 1, tier S
        $this->rate($tieA, 'B');
        $this->rate($tieB, 'S');

        $ranking = $this->props($this->visit([1 => $lucius, 4 => $brutus]))['ranking'];

        $this->assertSame(['Strong', 'Double', 'TieB', 'TieA'], array_column(array_column($ranking, 'hero'), 'name'));
        $this->assertSame([4, 2, 1, 1], array_column($ranking, 'total_strength'));
        $this->assertSame('S', $ranking[2]['tier']);
    }

    public function test_each_ranked_hero_carries_its_counters_with_reason_and_source(): void
    {
        [$lucius, $fawkes] = [$this->makeHero('Lucius'), $this->makeHero('Fawkes')];
        $this->own($fawkes);
        $this->counter($fawkes, $lucius, 2);

        $entry = $this->props($this->visit([1 => $lucius]))['ranking'][0];

        $this->assertSame('Fawkes', $entry['hero']['name']);
        $this->assertSame([[
            'target' => ['id' => $lucius->id, 'name' => 'Lucius'],
            'strength' => 2,
            'reason' => 'Fawkes vence Lucius',
            'source_url' => 'https://afk.guide/?p=2337',
        ]], $entry['counters']);
    }

    public function test_only_roster_heroes_that_counter_the_team_are_ranked(): void
    {
        [$lucius, $fawkes, $idle, $notOwned] = [$this->makeHero('Lucius'), $this->makeHero('Fawkes'), $this->makeHero('Idle'), $this->makeHero('NotOwned')];
        $this->own($fawkes, $idle);
        $this->counter($fawkes, $lucius);
        $this->counter($notOwned, $lucius, 2);

        $names = array_column(array_column($this->props($this->visit([1 => $lucius]))['ranking'], 'hero'), 'name');

        $this->assertSame(['Fawkes'], $names);
    }

    public function test_counters_from_other_contexts_do_not_count_in_pvp(): void
    {
        [$lucius, $fawkes] = [$this->makeHero('Lucius'), $this->makeHero('Fawkes')];
        $this->own($fawkes);
        $this->counter($fawkes, $lucius, 2, 'pve');

        $this->assertSame([], $this->props($this->visit([1 => $lucius]))['ranking']);
    }

    public function test_invalid_enemy_teams_are_rejected(): void
    {
        $heroes = collect(range(1, 6))->map(fn (int $i) => $this->makeHero("Hero{$i}"));
        $shemira = $this->makeHero('Shemira');
        $awakened = $this->makeHero('Shemira', 'shemira-the-soulreaver');

        $this->actingAs($this->user)->get('/counters?enemy=')->assertSessionHasErrors('enemy');
        $this->actingAs($this->user)->get('/counters?'.http_build_query(['enemy' => [6 => $heroes[0]->id]]))->assertSessionHasErrors('enemy');
        $this->actingAs($this->user)->get('/counters?'.http_build_query(['enemy' => [1 => $heroes[0]->id, 2 => $heroes[0]->id]]))->assertSessionHasErrors('enemy');
        $this->actingAs($this->user)->get('/counters?'.http_build_query(['enemy' => [1 => $shemira->id, 2 => $awakened->id]]))->assertSessionHasErrors('enemy');
    }

    public function test_more_than_five_enemies_odd_positions_and_pending_heroes_are_rejected(): void
    {
        $heroes = collect(range(1, 6))->map(fn (int $i) => $this->makeHero("Hero{$i}"));
        $pending = $this->makeHero('Pending');
        $pending->update(['pending_review' => true]);
        $get = fn (array $enemy) => $this->actingAs($this->user)->get('/counters?'.http_build_query(['enemy' => $enemy]));

        $get($heroes->mapWithKeys(fn (Hero $hero, int $i) => [$i + 1 => $hero->id])->all())->assertSessionHasErrors('enemy');
        $get(['1.5' => $heroes[0]->id])->assertSessionHasErrors('enemy');
        $get(['01' => $heroes[0]->id, '1' => $heroes[1]->id])->assertSessionHasErrors('enemy');
        $get([1 => $pending->id])->assertSessionHasErrors('enemy.1');
    }

    public function test_tie_break_ignores_ratings_from_other_contexts(): void
    {
        $lucius = $this->makeHero('Lucius');
        [$pveStar, $pvpStar] = [$this->makeHero('PveStar'), $this->makeHero('PvpStar')];
        $this->own($pveStar, $pvpStar);
        $this->counter($pveStar, $lucius);
        $this->counter($pvpStar, $lucius);
        $this->rate($pveStar, 'S+', 'pve');
        $this->rate($pvpStar, 'B');

        $ranking = $this->props($this->visit([1 => $lucius]))['ranking'];

        $this->assertSame(['PvpStar', 'PveStar'], array_column(array_column($ranking, 'hero'), 'name'));
        $this->assertNull($ranking[1]['tier']);
    }

    public function test_an_inactive_roster_hero_still_counts_in_real_planning(): void
    {
        [$lucius, $retired] = [$this->makeHero('Lucius'), $this->makeHero('Retired')];
        $this->own($retired);
        $this->counter($retired, $lucius);
        $retired->update(['is_active' => false]);

        $names = array_column(array_column($this->props($this->visit([1 => $lucius]))['ranking'], 'hero'), 'name');

        $this->assertSame(['Retired'], $names);
    }

    public function test_ranking_uses_the_active_game_account_roster_only(): void
    {
        [$lucius, $fawkes] = [$this->makeHero('Lucius'), $this->makeHero('Fawkes')];
        $this->counter($fawkes, $lucius);
        $other = User::factory()->create();
        $other->defaultGameAccount->userHeroes()->create(['hero_id' => $fawkes->id, 'level' => 240]);

        $response = $this->actingAs($this->user)
            ->withSession([ActiveGameAccount::SESSION_KEY => $other->defaultGameAccount->id])
            ->get('/counters?'.http_build_query(['enemy' => [1 => $lucius->id]]));

        $this->assertSame([], $this->props($response)['ranking']);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/counters')->assertRedirect('/login');
    }
}
