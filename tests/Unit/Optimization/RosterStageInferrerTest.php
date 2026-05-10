<?php

namespace Tests\Unit\Optimization;

use App\Models\HeroRating;
use App\Models\UserHero;
use App\Services\Optimization\RosterStageInferrer;
use PHPUnit\Framework\TestCase;

class RosterStageInferrerTest extends TestCase
{
    private RosterStageInferrer $inferrer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inferrer = new RosterStageInferrer;
    }

    private function makeRoster(array $ascensions): array
    {
        $roster = [];
        foreach ($ascensions as $i => $asc) {
            $uh = new UserHero;
            $uh->ascension_value = $asc;
            $roster[$i] = $uh;
        }
        return $roster;
    }

    public function test_avg_below_5_eh_early(): void
    {
        $roster = $this->makeRoster([0, 2, 3, 4]);  // avg 2.25
        $this->assertSame(HeroRating::STAGE_EARLY, $this->inferrer->inferFromRoster($roster));
    }

    public function test_avg_entre_5_e_10_eh_mid(): void
    {
        $roster = $this->makeRoster([5, 6, 8, 9]);  // avg 7
        $this->assertSame(HeroRating::STAGE_MID, $this->inferrer->inferFromRoster($roster));
    }

    public function test_avg_acima_de_10_eh_end(): void
    {
        $roster = $this->makeRoster([10, 11, 12, 13]);  // avg 11.5
        $this->assertSame(HeroRating::STAGE_END, $this->inferrer->inferFromRoster($roster));
    }

    public function test_roster_vazio_default_eh_end(): void
    {
        $this->assertSame(HeroRating::STAGE_END, $this->inferrer->inferFromRoster([]));
    }

    public function test_threshold_de_5_eh_inclusivo(): void
    {
        // avg exatamente 5.0 → mid (não early)
        $roster = $this->makeRoster([5, 5, 5]);
        $this->assertSame(HeroRating::STAGE_MID, $this->inferrer->inferFromRoster($roster));
    }
}
