<?php

namespace Tests\Feature;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroFurnitureAbility;
use App\Models\Tier;
use Database\Seeders\FactionSeeder;
use Database\Seeders\HeroClassSeeder;
use Database\Seeders\TierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassifyFurnitureEffectsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([FactionSeeder::class, HeroClassSeeder::class, TierSeeder::class]);
    }

    private function makeHero(string $slug): Hero
    {
        return Hero::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'faction_id' => Faction::where('code', 'lightbearer')->value('id'),
            'hero_class_id' => HeroClass::where('code', 'warrior')->value('id'),
            'tier_id' => Tier::where('code', 'ascended')->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_popula_effects_baseado_nas_descriptions(): void
    {
        $hero = $this->makeHero('test-hero-1');
        HeroFurnitureAbility::create([
            'hero_id' => $hero->id,
            'name' => 'Test Ability',
            'f3_description' => 'All negative control abilities are removed from the hero.',
            'f9_description' => 'Hero gains 500 Energy points at the beginning of battles.',
        ]);

        $this->artisan('furniture:classify-effects')->assertExitCode(0);

        $ability = HeroFurnitureAbility::where('hero_id', $hero->id)->first();
        $this->assertIsArray($ability->effects);
        $this->assertContains('cc_immunity', $ability->effects['f3']);
        $this->assertContains('energy_boost', $ability->effects['f9']);
    }

    public function test_pula_abilities_ja_classificadas_sem_force(): void
    {
        $hero = $this->makeHero('test-hero-2');
        HeroFurnitureAbility::create([
            'hero_id' => $hero->id,
            'name' => 'Test',
            'f3_description' => 'Damage is increased by 50%.',
            'effects' => ['f3' => ['custom_marker']],
        ]);

        $this->artisan('furniture:classify-effects')->assertExitCode(0);

        $ability = HeroFurnitureAbility::where('hero_id', $hero->id)->first();
        // Não deve ter sido reclassificada (mantém custom_marker)
        $this->assertContains('custom_marker', $ability->effects['f3']);
    }

    public function test_force_reclassifica_mesmo_com_effects(): void
    {
        $hero = $this->makeHero('test-hero-3');
        HeroFurnitureAbility::create([
            'hero_id' => $hero->id,
            'name' => 'Test',
            'f3_description' => 'Damage is increased by 50%.',
            'effects' => ['f3' => ['custom_marker']],
        ]);

        $this->artisan('furniture:classify-effects', ['--force' => true])->assertExitCode(0);

        $ability = HeroFurnitureAbility::where('hero_id', $hero->id)->first();
        $this->assertNotContains('custom_marker', $ability->effects['f3']);
        $this->assertContains('damage_boost', $ability->effects['f3']);
    }

    public function test_slug_filtra_para_um_hero(): void
    {
        $h1 = $this->makeHero('test-hero-a');
        $h2 = $this->makeHero('test-hero-b');
        HeroFurnitureAbility::create([
            'hero_id' => $h1->id,
            'name' => 'A',
            'f3_description' => 'Damage is increased by 50%.',
        ]);
        HeroFurnitureAbility::create([
            'hero_id' => $h2->id,
            'name' => 'B',
            'f3_description' => 'Heals all allies by 30%.',
        ]);

        $this->artisan('furniture:classify-effects', ['--slug' => 'test-hero-a'])->assertExitCode(0);

        $a = HeroFurnitureAbility::where('hero_id', $h1->id)->first();
        $b = HeroFurnitureAbility::where('hero_id', $h2->id)->first();

        $this->assertNotNull($a->effects);
        $this->assertNull($b->effects);
    }

    public function test_sem_abilities_relatorio_amigavel(): void
    {
        // nenhuma ability — comando deve sair normalmente
        $this->artisan('furniture:classify-effects')->assertExitCode(0);
    }
}
