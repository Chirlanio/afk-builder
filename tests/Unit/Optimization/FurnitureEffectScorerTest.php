<?php

namespace Tests\Unit\Optimization;

use App\Models\Hero;
use App\Models\HeroFurnitureAbility;
use App\Models\Tier;
use App\Models\UserHero;
use App\Services\Optimization\FurnitureEffectScorer;
use App\Services\Optimization\ScoringProfile;
use PHPUnit\Framework\TestCase;

class FurnitureEffectScorerTest extends TestCase
{
    private FurnitureEffectScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new FurnitureEffectScorer;
    }

    private function makeHero(int $id, ?string $tierCode = null): Hero
    {
        $hero = new Hero;
        $hero->id = $id;
        if ($tierCode !== null) {
            $tier = new Tier(['code' => $tierCode]);
            $hero->setRelation('tier', $tier);
        }

        return $hero;
    }

    private function makeAbility(int $heroId, array $effects): HeroFurnitureAbility
    {
        $a = new HeroFurnitureAbility;
        $a->hero_id = $heroId;
        $a->effects = $effects;

        return $a;
    }

    private function makeUserHero(int $heroId, int $furniture): UserHero
    {
        $uh = new UserHero;
        $uh->hero_id = $heroId;
        $uh->furniture = $furniture;

        return $uh;
    }

    public function test_retorna_zero_quando_nao_ha_abilities(): void
    {
        $heroes = [$this->makeHero(1)];
        $score = $this->scorer->score($heroes, [], [], new ScoringProfile);
        $this->assertSame(0.0, $score);
    }

    public function test_furniture_abaixo_de_3_nao_ativa_nada(): void
    {
        $hero = $this->makeHero(1);
        $userHero = $this->makeUserHero(1, 2);
        $ability = $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['damage_boost']]);

        $score = $this->scorer->score([$hero], [1 => $userHero], [1 => $ability], new ScoringProfile);
        $this->assertSame(0.0, $score);
    }

    public function test_furniture_3_ativa_apenas_f3_com_peso_meio(): void
    {
        $hero = $this->makeHero(1);
        $userHero = $this->makeUserHero(1, 5);
        $ability = $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['damage_boost']]);

        // damage_boost = 1.0 default × 0.5 (F3 multiplier) = 0.5
        $score = $this->scorer->score([$hero], [1 => $userHero], [1 => $ability], new ScoringProfile);
        $this->assertEqualsWithDelta(0.5, $score, 0.001);
    }

    public function test_furniture_9_ativa_f3_e_f9_cumulativos(): void
    {
        $hero = $this->makeHero(1);
        $userHero = $this->makeUserHero(1, 9);
        $ability = $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['cc_immunity']]);

        // F3 damage_boost = 1.0 × 0.5 = 0.5
        // F9 cc_immunity = 0.8 × 1.0 = 0.8
        // total = 1.3
        $score = $this->scorer->score([$hero], [1 => $userHero], [1 => $ability], new ScoringProfile);
        $this->assertEqualsWithDelta(1.3, $score, 0.001);
    }

    public function test_modo_target_ascended_assume_f3_e_f9_unlocked(): void
    {
        $hero = $this->makeHero(1, 'ascended');
        $ability = $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['cc_immunity']]);

        // Mesma fórmula do F9 unlocked acima = 1.3 (sem userHero, usa tier proxy)
        $score = $this->scorer->score([$hero], [], [1 => $ability], new ScoringProfile);
        $this->assertEqualsWithDelta(1.3, $score, 0.001);
    }

    public function test_modo_target_legendary_so_f3_unlocked(): void
    {
        $hero = $this->makeHero(1, 'legendary');
        $ability = $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['cc_immunity']]);

        // Só F3 ativo: damage_boost 1.0 × 0.5 = 0.5
        $score = $this->scorer->score([$hero], [], [1 => $ability], new ScoringProfile);
        $this->assertEqualsWithDelta(0.5, $score, 0.001);
    }

    public function test_modo_target_common_nao_ativa_nada(): void
    {
        $hero = $this->makeHero(1, 'common');
        $ability = $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['cc_immunity']]);

        $score = $this->scorer->score([$hero], [], [1 => $ability], new ScoringProfile);
        $this->assertSame(0.0, $score);
    }

    public function test_profile_pvp_amplifica_cc_immunity(): void
    {
        $hero = $this->makeHero(1);
        $userHero = $this->makeUserHero(1, 9);
        $ability = $this->makeAbility(1, ['f9' => ['cc_immunity']]);

        $pvpProfile = new ScoringProfile(furnitureEffectTagWeights: ['cc_immunity' => 1.4]);

        // cc_immunity F9: 1.4 × 1.0 = 1.4
        $score = $this->scorer->score([$hero], [1 => $userHero], [1 => $ability], $pvpProfile);
        $this->assertEqualsWithDelta(1.4, $score, 0.001);
    }

    public function test_profile_dream_realm_amplifica_damage_boost(): void
    {
        $hero = $this->makeHero(1);
        $userHero = $this->makeUserHero(1, 9);
        $ability = $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['damage_boost']]);

        $dreamProfile = new ScoringProfile(furnitureEffectTagWeights: ['damage_boost' => 1.5]);

        // F3: 1.5 × 0.5 = 0.75 + F9: 1.5 × 1.0 = 1.5 → total 2.25
        $score = $this->scorer->score([$hero], [1 => $userHero], [1 => $ability], $dreamProfile);
        $this->assertEqualsWithDelta(2.25, $score, 0.001);
    }

    public function test_team_completo_soma_contribuicoes(): void
    {
        $heroes = [
            $this->makeHero(1),
            $this->makeHero(2),
            $this->makeHero(3),
        ];
        $userHeroes = [
            1 => $this->makeUserHero(1, 9),  // F3 + F9
            2 => $this->makeUserHero(2, 3),  // só F3
            3 => $this->makeUserHero(3, 0),  // nada
        ];
        $abilities = [
            1 => $this->makeAbility(1, ['f3' => ['damage_boost'], 'f9' => ['damage_boost']]),
            2 => $this->makeAbility(2, ['f3' => ['shield']]),
            3 => $this->makeAbility(3, ['f9' => ['cc_immunity']]),
        ];

        // Hero 1: damage_boost F3 (0.5) + damage_boost F9 (1.0) = 1.5
        // Hero 2: shield F3 (0.7 × 0.5) = 0.35
        // Hero 3: 0 (furniture 0)
        // total = 1.85
        $score = $this->scorer->score($heroes, $userHeroes, $abilities, new ScoringProfile);
        $this->assertEqualsWithDelta(1.85, $score, 0.001);
    }

    public function test_tag_desconhecida_no_effects_ignorada(): void
    {
        $hero = $this->makeHero(1);
        $userHero = $this->makeUserHero(1, 9);
        $ability = $this->makeAbility(1, ['f9' => ['tag_inexistente', 'damage_boost']]);

        // só damage_boost conta: 1.0 × 1.0 = 1.0
        $score = $this->scorer->score([$hero], [1 => $userHero], [1 => $ability], new ScoringProfile);
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    public function test_effects_vazio_nao_quebra(): void
    {
        $hero = $this->makeHero(1);
        $userHero = $this->makeUserHero(1, 9);
        $ability = $this->makeAbility(1, []);

        $score = $this->scorer->score([$hero], [1 => $userHero], [1 => $ability], new ScoringProfile);
        $this->assertSame(0.0, $score);
    }
}
