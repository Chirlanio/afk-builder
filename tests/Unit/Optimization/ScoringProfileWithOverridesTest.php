<?php

namespace Tests\Unit\Optimization;

use App\Services\Optimization\ScoringProfile;
use PHPUnit\Framework\TestCase;

class ScoringProfileWithOverridesTest extends TestCase
{
    public function test_overrides_aplicam_apenas_chaves_passadas(): void
    {
        $profile = new ScoringProfile(
            factionBonusWeight: 1.2,
            classBalanceWeight: 0.9,
            synergyWeight: 1.5,
            powerWeight: 1.0,
            tierRatingWeight: 1.0,
            featureWeight: 0.5,
            furnitureEffectWeight: 0.4,
        );

        $tuned = $profile->withOverrides([
            'power_weight' => 2.5,
            'synergy_weight' => 0.3,
        ]);

        $this->assertSame(2.5, $tuned->powerWeight);
        $this->assertSame(0.3, $tuned->synergyWeight);
        // Demais preservados
        $this->assertSame(1.2, $tuned->factionBonusWeight);
        $this->assertSame(0.9, $tuned->classBalanceWeight);
        $this->assertSame(0.5, $tuned->featureWeight);
        $this->assertSame(0.4, $tuned->furnitureEffectWeight);
    }

    public function test_overrides_preservam_flags_e_tag_weights(): void
    {
        $tagWeights = ['cc_immunity' => 1.4, 'damage_boost' => 1.5];
        $profile = new ScoringProfile(
            furnitureEffectTagWeights: $tagWeights,
            pvp: true,
            dpsFocus: false,
            partitionOptimization: true,
        );

        $tuned = $profile->withOverrides(['power_weight' => 2.0]);

        $this->assertSame(2.0, $tuned->powerWeight);
        $this->assertSame($tagWeights, $tuned->furnitureEffectTagWeights);
        $this->assertTrue($tuned->pvp);
        $this->assertFalse($tuned->dpsFocus);
        $this->assertTrue($tuned->partitionOptimization);
    }

    public function test_overrides_vazio_retorna_profile_identico_em_pesos(): void
    {
        $profile = new ScoringProfile(powerWeight: 1.7, synergyWeight: 1.3);
        $tuned = $profile->withOverrides([]);

        $this->assertSame($profile->powerWeight, $tuned->powerWeight);
        $this->assertSame($profile->synergyWeight, $tuned->synergyWeight);
    }

    public function test_overrides_aceitam_string_numerica(): void
    {
        $profile = new ScoringProfile;
        $tuned = $profile->withOverrides(['power_weight' => '2.5']);

        $this->assertSame(2.5, $tuned->powerWeight);
    }
}
