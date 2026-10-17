<?php

namespace Tests\Unit\Optimization;

use App\Models\Hero;
use App\Services\Optimization\HeroCapabilityClassifier as Cap;
use App\Services\Optimization\TeamCapabilityAnalyzer;
use PHPUnit\Framework\TestCase;

class TeamCapabilityAnalyzerTest extends TestCase
{
    private TeamCapabilityAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new TeamCapabilityAnalyzer;
    }

    private function hero(string $slug, array $capabilities, ?array $manual = null): Hero
    {
        $hero = new Hero(['slug' => $slug]);
        $hero->capabilities = $capabilities;
        $hero->capabilities_manual = $manual;

        return $hero;
    }

    public function test_lista_quem_fornece_cada_capacidade(): void
    {
        $result = $this->analyzer->analyze([
            $this->hero('lucius', [Cap::HEALING => 1, Cap::FRONTLINE => 1]),
            $this->hero('rowan', [Cap::HEALING => 1, Cap::ENERGY_GAIN => 1]),
        ], 'campaign');

        $this->assertSame('pve', $result['kind']);
        $this->assertSame(['lucius', 'rowan'], $result['covered'][Cap::HEALING]);
        $this->assertSame(['rowan'], $result['covered'][Cap::ENERGY_GAIN]);
        $this->assertFalse($result['no_sustain']);
    }

    public function test_aponta_o_que_falta_no_contexto(): void
    {
        $result = $this->analyzer->analyze([$this->hero('nara', [Cap::DAMAGE_SINGLE => 1])], 'arena_of_heroes');

        $this->assertSame('pvp', $result['kind']);
        $this->assertContains(Cap::FRONTLINE, $result['missing']);
        $this->assertContains(Cap::CC_HARD, $result['missing']);
        $this->assertNotContains(Cap::DAMAGE_SINGLE, $result['missing']);
        $this->assertTrue($result['no_sustain']);
    }

    public function test_controle_nao_tem_efeito_contra_chefe(): void
    {
        $team = [$this->hero('solise', [Cap::CC_HARD => 2, Cap::HEALING => 1])];

        $boss = $this->analyzer->analyze($team, 'cursed_realm');
        $this->assertSame('boss', $boss['kind']);
        $this->assertSame(['solise'], $boss['ineffective'][Cap::CC_HARD]);

        $campaign = $this->analyzer->analyze($team, 'campaign');
        $this->assertSame([], $campaign['ineffective']);
    }

    public function test_usa_as_correcoes_do_admin(): void
    {
        $result = $this->analyzer->analyze([
            $this->hero('brutus', [Cap::FRONTLINE => 1], ['add' => [Cap::SELF_SUSTAIN => 2], 'remove' => [Cap::FRONTLINE]]),
        ], 'campaign');

        $this->assertArrayHasKey(Cap::SELF_SUSTAIN, $result['covered']);
        $this->assertArrayNotHasKey(Cap::FRONTLINE, $result['covered']);
    }

    public function test_sem_perfis_nao_aponta_faltas(): void
    {
        $result = $this->analyzer->analyze([$this->hero('a', []), $this->hero('b', [])], 'campaign');

        $this->assertFalse($result['has_profiles']);
        $this->assertSame([], $result['missing']);
        $this->assertFalse($result['no_sustain']);
    }
}
