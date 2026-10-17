<?php

namespace Tests\Feature;

use App\Models\Beast;
use Database\Seeders\BeastTiersSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HeroEngravingsSeeder;
use Database\Seeders\HeroFeaturesSeeder;
use Database\Seeders\RecommendedFormationsSeeder;
use Database\Seeders\SignatureItemsGuideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test dos seeders que ficam fora do DatabaseSeeder e só rodam depois do
 * sync do catálogo (deploy/hostinger/catalog.sh). Sem rodá-los nos testes, uma
 * mudança de schema os quebra em silêncio — foi o que aconteceu com o
 * BeastTiersSeeder após a Fase 51 (colunas *_buff removidas).
 */
class CatalogSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_do_catalogo_rodam_em_banco_recem_semeado(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->seed([
            HeroEngravingsSeeder::class,
            HeroFeaturesSeeder::class,
            BeastTiersSeeder::class,
            RecommendedFormationsSeeder::class,
            SignatureItemsGuideSeeder::class,
        ]);

        $this->assertTrue(Beast::where('slug', 'count-snickerfang')->exists());
    }

    public function test_beast_tiers_seeder_atualiza_beast_existente_sem_mexer_nos_buffs(): void
    {
        Beast::create([
            'slug' => 'slumber-seal',
            'name' => 'Slumber Seal',
            'buffs_team' => ['attack' => 10],
        ]);

        $this->seed(BeastTiersSeeder::class);

        $beast = Beast::where('slug', 'slumber-seal')->sole();
        $this->assertSame('S+', $beast->tier);
        $this->assertSame(['attack' => 10], $beast->buffs_team);
    }
}
