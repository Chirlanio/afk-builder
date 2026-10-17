<?php

namespace Tests\Unit\Optimization;

use App\Models\HeroRating;
use App\Services\Optimization\ChallengeTypeMode;
use PHPUnit\Framework\TestCase;

class ChallengeTypeModeTest extends TestCase
{
    public function test_pvp_codes(): void
    {
        $this->assertSame(HeroRating::MODE_PVP, ChallengeTypeMode::forCode('arena_of_heroes'));
        $this->assertSame(HeroRating::MODE_PVP, ChallengeTypeMode::forCode('legends_championship'));
        $this->assertSame(HeroRating::MODE_PVP, ChallengeTypeMode::forCode('heroes_of_esperia'));
        $this->assertTrue(ChallengeTypeMode::isPvp('arena_of_heroes'));
    }

    public function test_pve_codes(): void
    {
        $this->assertSame(HeroRating::MODE_PVE, ChallengeTypeMode::forCode('campaign'));
        $this->assertSame(HeroRating::MODE_PVE, ChallengeTypeMode::forCode('kings_tower'));
        $this->assertSame(HeroRating::MODE_PVE, ChallengeTypeMode::forCode('cursed_realm'));
        $this->assertFalse(ChallengeTypeMode::isPvp('campaign'));
    }

    public function test_special_codes(): void
    {
        $this->assertSame(HeroRating::MODE_TWISTED_REALM, ChallengeTypeMode::forCode('dream_realm'));
        $this->assertSame(HeroRating::MODE_LABYRINTH, ChallengeTypeMode::forCode('arcane_labyrinth'));
        $this->assertSame(HeroRating::MODE_ABYSSAL_EXPEDITION, ChallengeTypeMode::forCode('abyssal_expedition'));
        // Provas da Forja Dracônica têm ratings próprios (derivados das formações do afk.guide).
        $this->assertSame(HeroRating::MODE_DRAGONFORGE, ChallengeTypeMode::forCode('dragon_trial'));
        $this->assertTrue(ChallengeTypeMode::isTwistedRealm('dream_realm'));
    }

    public function test_unknown_code_falls_back_to_pve(): void
    {
        $this->assertSame(HeroRating::MODE_PVE, ChallengeTypeMode::forCode('unknown_mode_xyz'));
        $this->assertSame(HeroRating::MODE_PVE, ChallengeTypeMode::forCode(null));
    }
}
