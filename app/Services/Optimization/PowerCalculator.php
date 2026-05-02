<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\UserHero;

class PowerCalculator
{
    private const TIER_BASELINE = [
        'common' => ['ascension' => 3, 'level' => 100],
        'legendary' => ['ascension' => 9, 'level' => 160],
        'ascended' => ['ascension' => 12, 'level' => 240],
    ];

    public function forUserHero(UserHero $userHero): float
    {
        return $userHero->ascension_value * 100
            + $userHero->level
            + $userHero->signature_item * 5
            + $userHero->furniture * 30
            + $userHero->engraving * 0.5;
    }

    public function targetBaseline(Hero $hero): float
    {
        $tierCode = $hero->tier?->code ?? 'common';
        $baseline = self::TIER_BASELINE[$tierCode] ?? self::TIER_BASELINE['common'];

        return $baseline['ascension'] * 100 + $baseline['level'];
    }

    public function forHero(Hero $hero, ?UserHero $userHero): float
    {
        return $userHero
            ? $this->forUserHero($userHero)
            : $this->targetBaseline($hero);
    }
}
