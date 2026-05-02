<?php

namespace Tests\Feature\Optimization;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Services\Optimization\PowerCalculator;
use App\Services\Optimization\ScoringProfile;
use App\Services\Optimization\SingleTeamOptimizer;
use App\Services\Optimization\TeamScorer;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleTeamOptimizerTest extends TestCase
{
    use RefreshDatabase;

    private SingleTeamOptimizer $optimizer;

    private ScoringProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);

        $this->optimizer = new SingleTeamOptimizer(new TeamScorer(new PowerCalculator));
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
     * @param  Hero[]  $heroes
     * @return array<int, Hero>
     */
    private function poolFrom(array $heroes): array
    {
        $map = [];
        foreach ($heroes as $h) {
            $map[$h->id] = $h;
        }

        return $map;
    }

    public function test_preenche_5_slots_vazios(): void
    {
        $tank = $this->makeHero('tank-lb', 'lightbearer', 'tank');
        $warrior = $this->makeHero('warrior-lb', 'lightbearer', 'warrior');
        $ranger = $this->makeHero('ranger-lb', 'lightbearer', 'ranger');
        $mage = $this->makeHero('mage-lb', 'lightbearer', 'mage');
        $support = $this->makeHero('support-lb', 'lightbearer', 'support');
        $extra = $this->makeHero('extra-mauler', 'mauler', 'warrior');

        $pool = $this->poolFrom([$tank, $warrior, $ranger, $mage, $support, $extra]);

        $result = $this->optimizer->optimize($pool, [], [], $this->profile, [], []);

        // Deve montar 5-stack lightbearer (todos os 5 LB do pool)
        $this->assertCount(5, $result['hero_ids']);
        $expected = [$tank->id, $warrior->id, $ranger->id, $mage->id, $support->id];
        sort($expected);
        $actual = $result['hero_ids'];
        sort($actual);
        $this->assertSame($expected, $actual);
        $this->assertSame(1.0, $result['score']->factionBonus);
    }

    public function test_preenche_apenas_2_slots_vazios_preservando_locked(): void
    {
        $locked1 = $this->makeHero('locked-1', 'wilder', 'tank');
        $locked2 = $this->makeHero('locked-2', 'wilder', 'warrior');
        $locked3 = $this->makeHero('locked-3', 'wilder', 'ranger');
        $candA = $this->makeHero('cand-wilder-mage', 'wilder', 'mage');
        $candB = $this->makeHero('cand-wilder-sup', 'wilder', 'support');
        $candC = $this->makeHero('cand-graveborn', 'graveborn', 'mage');

        $pool = $this->poolFrom([$locked1, $locked2, $locked3, $candA, $candB, $candC]);

        $result = $this->optimizer->optimize(
            $pool, [], [], $this->profile,
            [$locked1->id, $locked2->id, $locked3->id],
            [],
        );

        // Os 3 locked devem estar no resultado
        $this->assertContains($locked1->id, $result['hero_ids']);
        $this->assertContains($locked2->id, $result['hero_ids']);
        $this->assertContains($locked3->id, $result['hero_ids']);
        $this->assertCount(5, $result['hero_ids']);
        // Otimizador deve preferir candA + candB (5x wilder = bônus máximo)
        $this->assertContains($candA->id, $result['hero_ids']);
        $this->assertContains($candB->id, $result['hero_ids']);
        $this->assertNotContains($candC->id, $result['hero_ids']);
    }

    public function test_respeita_blocked_de_outras_equipes(): void
    {
        $hero1 = $this->makeHero('h1', 'lightbearer', 'tank');
        $hero2 = $this->makeHero('h2', 'lightbearer', 'warrior');
        $hero3 = $this->makeHero('h3', 'lightbearer', 'ranger');
        $hero4 = $this->makeHero('h4', 'lightbearer', 'mage');
        $hero5 = $this->makeHero('h5', 'lightbearer', 'support');
        // Substitutos não-LB para o caso de bloqueio
        $sub1 = $this->makeHero('s1', 'mauler', 'tank');
        $sub2 = $this->makeHero('s2', 'mauler', 'warrior');

        $pool = $this->poolFrom([$hero1, $hero2, $hero3, $hero4, $hero5, $sub1, $sub2]);

        // hero4 e hero5 já estão em outra equipe (blocked)
        $result = $this->optimizer->optimize(
            $pool, [], [], $this->profile,
            [],
            [$hero4->id, $hero5->id],
        );

        $this->assertNotContains($hero4->id, $result['hero_ids']);
        $this->assertNotContains($hero5->id, $result['hero_ids']);
        $this->assertCount(5, $result['hero_ids']);
    }

    public function test_pool_menor_que_5_retorna_so_o_disponivel(): void
    {
        $h1 = $this->makeHero('h1', 'lightbearer', 'tank');
        $h2 = $this->makeHero('h2', 'lightbearer', 'warrior');
        $h3 = $this->makeHero('h3', 'lightbearer', 'ranger');

        $pool = $this->poolFrom([$h1, $h2, $h3]);

        $result = $this->optimizer->optimize($pool, [], [], $this->profile, [], []);

        $this->assertCount(3, $result['hero_ids']);
    }

    public function test_synergia_aumenta_a_chance_de_par_ser_escolhido(): void
    {
        $tank = $this->makeHero('tank', 'mauler', 'tank');
        $heroA = $this->makeHero('a', 'wilder', 'warrior');
        $heroB = $this->makeHero('b', 'wilder', 'ranger');
        $partnerA = $this->makeHero('partner-a', 'graveborn', 'mage');
        $partnerB = $this->makeHero('partner-b', 'graveborn', 'support');

        $pool = $this->poolFrom([$tank, $heroA, $heroB, $partnerA, $partnerB]);

        // Synergia forte entre heroA e partnerA (peso 50)
        $synergyWeights = [TeamScorer::pairKey($heroA->id, $partnerA->id) => 50];

        $result = $this->optimizer->optimize($pool, [], $synergyWeights, $this->profile, [$tank->id], []);

        // Com synergia tão forte, heroA + partnerA devem ser escolhidos
        $this->assertContains($heroA->id, $result['hero_ids']);
        $this->assertContains($partnerA->id, $result['hero_ids']);
    }
}
