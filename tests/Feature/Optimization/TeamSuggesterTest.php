<?php

namespace Tests\Feature\Optimization;

use App\Enums\Planning;
use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Formation;
use App\Models\GameAccount;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroRating;
use App\Models\Tier;
use App\Models\User;
use App\Services\Optimization\TeamSuggester;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSuggesterTest extends TestCase
{
    use RefreshDatabase;

    private const CLASSES = ['tank', 'warrior', 'ranger', 'mage', 'support'];

    private GameAccount $account;

    /** @var int[] */
    private array $owned = [];

    /** @var int[] */
    private array $best = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class, SignatureItemCostsSeeder::class]);

        $this->account = User::factory()->create()->defaultGameAccount;

        // Roster: investido no máximo, mas sem rating.
        foreach (self::CLASSES as $i => $class) {
            $hero = $this->makeHero("owned-{$i}", 'lightbearer', $class);
            $this->account->userHeroes()->create([
                'hero_id' => $hero->id, 'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars',
                'signature_item' => 40, 'furniture' => 9, 'engraving' => 80,
            ]);
            $this->owned[] = $hero->id;
        }

        // Fora do Roster: os mais bem avaliados do Contexto.
        foreach (self::CLASSES as $i => $class) {
            $hero = $this->makeHero("best-{$i}", 'mauler', $class);
            HeroRating::create(['hero_id' => $hero->id, 'mode' => 'pve', 'stage' => 'end', 'tier' => 'S+', 'source' => 'afk.guide']);
            $this->best[] = $hero->id;
        }
    }

    private function makeHero(string $slug, string $faction, string $class): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $faction)->value('id'),
            'hero_class_id' => HeroClass::where('code', $class)->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
    }

    private function campaign(): ChallengeType
    {
        return ChallengeType::where('code', 'campaign')->firstOrFail();
    }

    public function test_target_planning_suggests_without_any_formation(): void
    {
        $result = app(TeamSuggester::class)->suggestAll($this->account, $this->campaign(), Planning::Target, 1);

        $suggested = collect($result['teams'][1])->sort()->values()->all();
        $this->assertSame($this->best, $suggested);
        $this->assertSame(0, Formation::count());
    }

    public function test_real_planning_only_uses_the_roster(): void
    {
        $result = app(TeamSuggester::class)->suggestAll($this->account, $this->campaign(), Planning::Real, 1);

        $suggested = collect($result['teams'][1])->sort()->values()->all();
        $this->assertSame($this->owned, $suggested);
    }

    public function test_multi_team_suggestion_never_repeats_a_hero_or_a_character(): void
    {
        $awakened = Hero::create([
            'slug' => 'best-0-the-reborn',
            'name' => 'Best-0',
            'faction_id' => Faction::where('code', 'mauler')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'tank')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ]);
        HeroRating::create(['hero_id' => $awakened->id, 'mode' => 'pve', 'stage' => 'end', 'tier' => 'S+', 'source' => 'afk.guide']);

        $result = app(TeamSuggester::class)->suggestAll($this->account, $this->campaign(), Planning::Target, 2);

        $all = array_merge($result['teams'][1], $result['teams'][2]);
        $this->assertCount(10, $all);
        $this->assertSame($all, array_values(array_unique($all)), 'nenhum herói em duas Equipes');
        $this->assertFalse(
            in_array($this->best[0], $all, true) && in_array($awakened->id, $all, true),
            'a versão base e a Desperta do mesmo Personagem não entram juntas',
        );
    }

    public function test_single_team_suggestion_respects_locked_and_blocked_heroes(): void
    {
        $locked = [$this->owned[0]];
        $blocked = [$this->best[0]];

        $result = app(TeamSuggester::class)->suggestTeam($this->account, $this->campaign(), Planning::Target, $locked, $blocked);

        $this->assertContains($this->owned[0], $result['hero_ids']);
        $this->assertNotContains($this->best[0], $result['hero_ids']);
        $this->assertCount(5, $result['hero_ids']);
    }
}
