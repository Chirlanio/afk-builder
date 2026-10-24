<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroCounter;
use App\Models\Tier;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\HeroCounterSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HeroCounterSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    /** Cria no catálogo todos os heróis citados pelo seeder, menos os de $skip. */
    private function seedCatalogHeroes(array $skip = []): void
    {
        $slugs = collect(HeroCounterSeeder::COUNTERS)->flatMap(fn (array $c) => [$c[0], $c[1]])->unique()->diff($skip);
        foreach ($slugs as $slug) {
            Hero::create([
                'slug' => $slug,
                'name' => Str::of($slug)->replace('-', ' ')->title()->toString(),
                'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
                'hero_class_id' => HeroClass::where('code', 'mage')->value('id'),
                'tier_id' => Tier::where('code', 'ascended')->value('id'),
                'is_active' => true,
                'pending_review' => false,
            ]);
        }
    }

    public function test_every_listed_counter_has_reason_source_valid_context_and_strength(): void
    {
        $this->assertGreaterThanOrEqual(30, count(HeroCounterSeeder::COUNTERS));

        foreach (HeroCounterSeeder::COUNTERS as [$hero, $target, $context, $strength, $reason, $url]) {
            $this->assertNotSame($hero, $target, "{$hero} contra ele mesmo");
            $this->assertContains($context, HeroCounter::CONTEXTS, "{$hero} → {$target}");
            $this->assertContains($strength, range(1, HeroCounter::MAX_STRENGTH), "{$hero} → {$target}");
            $this->assertNotSame('', trim($reason), "{$hero} → {$target} sem motivo");
            $this->assertStringStartsWith('https://afk.guide/', $url, "{$hero} → {$target} sem fonte");
        }
    }

    public function test_the_list_has_no_repeated_pair_in_the_same_context(): void
    {
        $keys = array_map(fn (array $c) => "{$c[0]}>{$c[1]}@{$c[2]}", HeroCounterSeeder::COUNTERS);

        $this->assertSame(count($keys), count(array_unique($keys)));
    }

    public function test_seeding_creates_the_counters_and_is_idempotent(): void
    {
        $this->seedCatalogHeroes();

        (new HeroCounterSeeder)->run();
        $first = HeroCounter::count();
        (new HeroCounterSeeder)->run();

        $this->assertSame(count(HeroCounterSeeder::COUNTERS), $first);
        $this->assertSame($first, HeroCounter::count());
        $this->assertSame(0, HeroCounter::whereNull('source_url')->count());
    }

    public function test_heroes_missing_from_the_catalog_are_skipped_without_breaking(): void
    {
        $this->seedCatalogHeroes(skip: ['fawkes']);

        (new HeroCounterSeeder)->run();

        $withFawkes = collect(HeroCounterSeeder::COUNTERS)->filter(fn (array $c) => in_array('fawkes', [$c[0], $c[1]], true))->count();
        $this->assertGreaterThan(0, $withFawkes);
        $this->assertSame(count(HeroCounterSeeder::COUNTERS) - $withFawkes, HeroCounter::count());
    }

    public function test_counters_between_versions_of_the_same_character_are_never_seeded(): void
    {
        $this->seedCatalogHeroes();
        // Uma versão Desperta do Lucius no catálogo: o mesmo Personagem, outro Herói.
        Hero::where('slug', 'fawkes')->update(['name' => 'Lucius']);

        (new HeroCounterSeeder)->run();

        foreach (HeroCounter::with(['hero', 'target'])->get() as $counter) {
            $this->assertNotSame($counter->hero->characterKey(), $counter->target->characterKey(), "{$counter->hero->slug} × {$counter->target->slug}");
        }
        $this->assertFalse(HeroCounter::whereHas('hero', fn ($q) => $q->where('slug', 'fawkes'))->whereHas('target', fn ($q) => $q->where('slug', 'lucius'))->exists());
    }
}
