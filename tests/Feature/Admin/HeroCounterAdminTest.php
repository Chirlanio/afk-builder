<?php

namespace Tests\Feature\Admin;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroCounter;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HeroCounterAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
        $this->admin = User::factory()->create(['is_admin' => true]);
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

    private function counter(Hero $hero, Hero $target, array $overrides = []): array
    {
        return $overrides + [
            'hero_id' => $hero->id,
            'target_hero_id' => $target->id,
            'context' => 'pvp',
            'strength' => 2,
            'reason' => 'Dissipa o escudo do alvo.',
            'source_url' => 'https://afk.guide/?p=2337',
        ];
    }

    public function test_admin_creates_a_counter_that_shows_on_both_heroes(): void
    {
        $fawkes = $this->makeHero('Fawkes');
        $lucius = $this->makeHero('Lucius');

        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius))->assertCreated();

        $this->actingAs($this->admin)->get("/admin/counters?hero={$fawkes->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/counters/index')
                ->where('hero.id', $fawkes->id)
                ->has('counters', 1)
                ->where('counters.0.target.name', 'Lucius')
                ->where('counters.0.strength', 2)
                ->where('counters.0.reason', 'Dissipa o escudo do alvo.')
                ->has('counteredBy', 0)
            );

        $this->actingAs($this->admin)->get("/admin/counters?hero={$lucius->id}")
            ->assertInertia(fn ($page) => $page
                ->has('counters', 0)
                ->has('counteredBy', 1)
                ->where('counteredBy.0.hero.name', 'Fawkes')
            );
    }

    public function test_a_hero_cannot_counter_itself(): void
    {
        $lucius = $this->makeHero('Lucius');

        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($lucius, $lucius))
            ->assertJsonValidationErrors('target_hero_id');
        $this->assertSame(0, HeroCounter::count());
    }

    public function test_base_and_awakened_versions_of_a_character_cannot_counter_each_other(): void
    {
        $shemira = $this->makeHero('Shemira');
        $awakened = $this->makeHero('Shemira', 'shemira-the-soulreaver');

        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($shemira, $awakened))
            ->assertJsonValidationErrors('target_hero_id');
        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($awakened, $shemira))
            ->assertJsonValidationErrors('target_hero_id');
    }

    public function test_the_same_pair_is_unique_per_context_but_allowed_in_another(): void
    {
        $fawkes = $this->makeHero('Fawkes');
        $lucius = $this->makeHero('Lucius');
        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius))->assertCreated();

        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius, ['strength' => 1]))
            ->assertJsonValidationErrors('target_hero_id');
        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius, ['context' => 'pve']))
            ->assertCreated();
    }

    public function test_mutual_counters_coexist(): void
    {
        $fawkes = $this->makeHero('Fawkes');
        $lucius = $this->makeHero('Lucius');

        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius))->assertCreated();
        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($lucius, $fawkes))->assertCreated();

        $this->assertSame(2, HeroCounter::count());
    }

    public function test_strength_out_of_range_and_empty_reason_are_rejected(): void
    {
        $fawkes = $this->makeHero('Fawkes');
        $lucius = $this->makeHero('Lucius');

        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius, ['strength' => 3]))
            ->assertJsonValidationErrors('strength');
        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius, ['reason' => '']))
            ->assertJsonValidationErrors('reason');
        $this->actingAs($this->admin)->postJson('/admin/counters', $this->counter($fawkes, $lucius, ['context' => 'overall']))
            ->assertJsonValidationErrors('context');
    }

    public function test_admin_edits_and_removes_a_counter(): void
    {
        $counter = HeroCounter::create($this->counter($this->makeHero('Fawkes'), $this->makeHero('Lucius')));

        $this->actingAs($this->admin)->patchJson("/admin/counters/{$counter->id}", [
            'strength' => 1, 'reason' => 'Só tira parte do escudo.', 'source_url' => null,
        ])->assertOk();
        $this->assertSame(1, $counter->fresh()->strength);
        $this->assertSame('Só tira parte do escudo.', $counter->fresh()->reason);
        $this->assertNull($counter->fresh()->source_url);

        $this->actingAs($this->admin)->deleteJson("/admin/counters/{$counter->id}")->assertOk();
        $this->assertSame(0, HeroCounter::count());
    }

    public function test_only_admins_reach_the_counters_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $counter = HeroCounter::create($this->counter($this->makeHero('Fawkes'), $this->makeHero('Lucius')));

        $this->get('/admin/counters')->assertRedirect('/login');
        $this->actingAs($user)->get('/admin/counters')->assertForbidden();
        $this->actingAs($user)->postJson('/admin/counters', [])->assertForbidden();
        $this->actingAs($user)->patchJson("/admin/counters/{$counter->id}", ['strength' => 1])->assertForbidden();
        $this->actingAs($user)->deleteJson("/admin/counters/{$counter->id}")->assertForbidden();
    }
}
