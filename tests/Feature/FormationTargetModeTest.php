<?php

namespace Tests\Feature;

use App\Models\ChallengeType;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroRating;
use App\Models\HeroStat;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\SignatureItemCostsSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormationTargetModeTest extends TestCase
{
    use RefreshDatabase;

    private const CLASSES = ['tank', 'warrior', 'ranger', 'mage', 'support'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class, SignatureItemCostsSeeder::class]);
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

    public function test_target_suggestions_ignore_roster_investment_and_game_screen_stats(): void
    {
        $user = User::factory()->create();
        $account = $user->defaultGameAccount;

        // Owned, fully invested, unrated — with game-screen values typed into the catalog.
        $owned = [];
        foreach (self::CLASSES as $i => $class) {
            $hero = $this->makeHero("owned-{$i}", 'lightbearer', $class);
            HeroStat::create(['hero_id' => $hero->id, 'hp' => 900_000_000, 'attack' => 45_000_000, 'defense' => 15_000_000]);
            $account->userHeroes()->create([
                'hero_id' => $hero->id, 'level' => 240, 'ascension_value' => 16, 'ascension_code' => 'ascended_5_stars',
                'signature_item' => 40, 'furniture' => 9, 'engraving' => 80,
            ]);
            $owned[] = $hero->id;
        }

        // Not owned, top rated.
        $best = [];
        foreach (self::CLASSES as $i => $class) {
            $hero = $this->makeHero("best-{$i}", 'mauler', $class);
            HeroRating::create(['hero_id' => $hero->id, 'mode' => 'pve', 'stage' => 'end', 'tier' => 'S+', 'source' => 'afk.guide']);
            $best[] = $hero->id;
        }

        $formation = $account->formations()->create([
            'challenge_type_id' => ChallengeType::where('code', 'campaign')->value('id'),
            'name' => 'Target',
            'mode' => 'target',
            'teams_count' => 1,
        ]);
        $formation->teams()->create(['position' => 1]);

        $response = $this->actingAs($user)
            ->postJson("/formations/{$formation->id}/suggest-all", ['teams' => [['position' => 1, 'heroes' => []]]])
            ->assertOk();

        $suggested = collect($response->json('teams.0.additions'))->pluck('hero_id')->sort()->values()->all();
        $this->assertSame($best, $suggested);
    }
}
