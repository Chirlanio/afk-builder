<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\RecommendedFormation;
use App\Models\Tier;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptimizerBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class, ChallengeTypeSeeder::class]);
    }

    public function test_sem_catalogo_falha_com_aviso(): void
    {
        $this->artisan('optimizer:benchmark', ['--samples' => 10])
            ->expectsOutputToContain('Catálogo vazio')
            ->assertFailed();
    }

    public function test_compara_comps_com_times_aleatorios(): void
    {
        $classes = ['tank', 'warrior', 'ranger', 'mage', 'support'];
        foreach (range(1, 12) as $i) {
            Hero::create([
                'slug' => "heroi-{$i}",
                'name' => "Herói {$i}",
                'faction_id' => Faction::where('code', $i <= 5 ? 'lightbearer' : 'mauler')->value('id'),
                'hero_class_id' => HeroClass::where('code', $classes[$i % 5])->value('id'),
                'tier_id' => Tier::where('code', 'ascended')->value('id'),
                'is_active' => true,
                'pending_review' => false,
            ]);
        }
        RecommendedFormation::create([
            'slug' => 'comp-teste', 'name' => 'Comp teste', 'mode' => RecommendedFormation::MODE_PVE,
            'hero_slugs' => ['heroi-1', 'heroi-2', 'heroi-3', 'heroi-4', 'heroi-5'], 'core_count' => 5, 'is_active' => true,
        ]);

        $this->artisan('optimizer:benchmark', ['--samples' => 50])
            ->expectsOutputToContain('1 comps × 50 times aleatórios por modo')
            ->assertSuccessful();
    }
}
