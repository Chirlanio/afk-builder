<?php

namespace Tests\Feature\Optimization;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Services\Optimization\MultiTeamPartitioner;
use App\Services\Optimization\PowerCalculator;
use App\Services\Optimization\ScoringProfile;
use App\Services\Optimization\SingleTeamOptimizer;
use App\Services\Optimization\TeamScorer;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTeamPartitionerTest extends TestCase
{
    use RefreshDatabase;

    private MultiTeamPartitioner $partitioner;

    private ScoringProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);

        $scorer = new TeamScorer(new PowerCalculator);
        $single = new SingleTeamOptimizer($scorer);
        $this->partitioner = new MultiTeamPartitioner($scorer, $single);
        $this->profile = new ScoringProfile;
    }

    private function makeHero(string $slug, string $faction, string $class, string $tier = 'ascended'): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', $faction)->value('id'),
            'hero_class_id' => HeroClass::where('code', $class)->value('id'),
            'tier_id' => Tier::where('code', $tier)->value('id'),
            'is_active' => true,
            'pending_review' => false,
        ])->load(['faction', 'heroClass', 'tier']);
    }

    /**
     * @return array<int, Hero>
     */
    private function makePool(int $count, string $faction = 'lightbearer'): array
    {
        $classes = ['tank', 'warrior', 'ranger', 'mage', 'support'];
        $factions = ['lightbearer', 'mauler', 'wilder', 'graveborn', 'celestial', 'hypogean', 'dimensional', 'draconis'];

        $pool = [];
        for ($i = 0; $i < $count; $i++) {
            $f = $faction === 'mixed' ? $factions[$i % count($factions)] : $faction;
            $h = $this->makeHero("hero-{$i}", $f, $classes[$i % 5]);
            $pool[$h->id] = $h;
        }

        return $pool;
    }

    public function test_distribui_3_equipes_unique_sem_sobreposicao(): void
    {
        $pool = $this->makePool(20, 'mixed');

        $result = $this->partitioner->optimize(
            $pool, [], [], $this->profile,
            [], 3, true,
        );

        $this->assertCount(3, $result['teams']);

        $allIds = [];
        foreach ($result['teams'] as $heroIds) {
            $this->assertLessThanOrEqual(5, count($heroIds));
            foreach ($heroIds as $id) {
                $allIds[] = $id;
            }
        }

        // Sem repetição entre equipes
        $this->assertSame(count($allIds), count(array_unique($allIds)));
    }

    public function test_preserva_locked_em_cada_equipe(): void
    {
        $pool = $this->makePool(15, 'mixed');
        $poolIds = array_keys($pool);

        $lockedByTeam = [
            1 => [$poolIds[0], $poolIds[1]],
            2 => [$poolIds[2]],
            3 => [],
        ];

        $result = $this->partitioner->optimize(
            $pool, [], [], $this->profile,
            $lockedByTeam, 3, true,
        );

        $this->assertContains($poolIds[0], $result['teams'][1]);
        $this->assertContains($poolIds[1], $result['teams'][1]);
        $this->assertContains($poolIds[2], $result['teams'][2]);
    }

    public function test_locked_em_uma_equipe_bloqueia_em_outras(): void
    {
        $pool = $this->makePool(20, 'mixed');
        $poolIds = array_keys($pool);

        $lockedByTeam = [
            1 => [$poolIds[0], $poolIds[1], $poolIds[2]],
            2 => [],
        ];

        $result = $this->partitioner->optimize(
            $pool, [], [], $this->profile,
            $lockedByTeam, 2, true,
        );

        // Heróis locked na equipe 1 não devem aparecer na 2
        $this->assertNotContains($poolIds[0], $result['teams'][2]);
        $this->assertNotContains($poolIds[1], $result['teams'][2]);
        $this->assertNotContains($poolIds[2], $result['teams'][2]);
    }

    public function test_pool_insuficiente_distribui_o_que_tem(): void
    {
        // 8 heróis para 3 equipes (precisaria 15 com unique=true)
        $pool = $this->makePool(8, 'mixed');

        $result = $this->partitioner->optimize(
            $pool, [], [], $this->profile,
            [], 3, true,
        );

        $allIds = [];
        foreach ($result['teams'] as $ids) {
            foreach ($ids as $id) {
                $allIds[] = $id;
            }
        }

        // Total distribuído ≤ tamanho do pool, sem repetição
        $this->assertLessThanOrEqual(8, count($allIds));
        $this->assertSame(count($allIds), count(array_unique($allIds)));
    }

    public function test_modo_nao_unique_pode_repetir_herois(): void
    {
        // Pool pequeno para garantir que o mesmo herói seja escolhido nas 2 equipes
        $h1 = $this->makeHero('top', 'lightbearer', 'tank');
        $h2 = $this->makeHero('h2', 'lightbearer', 'warrior');
        $h3 = $this->makeHero('h3', 'lightbearer', 'ranger');
        $h4 = $this->makeHero('h4', 'lightbearer', 'mage');
        $h5 = $this->makeHero('h5', 'lightbearer', 'support');
        $pool = [$h1->id => $h1, $h2->id => $h2, $h3->id => $h3, $h4->id => $h4, $h5->id => $h5];

        $result = $this->partitioner->optimize(
            $pool, [], [], $this->profile,
            [], 2, false, // não-unique
        );

        // Como não-unique, os mesmos 5 heróis aparecem nas 2 equipes
        $this->assertCount(5, $result['teams'][1]);
        $this->assertCount(5, $result['teams'][2]);
        sort($result['teams'][1]);
        sort($result['teams'][2]);
        $this->assertSame($result['teams'][1], $result['teams'][2]);
    }
}
