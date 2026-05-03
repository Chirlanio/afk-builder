<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\HeroStat;
use App\Models\UserHero;

class PowerCalculator
{
    private const TIER_BASELINE = [
        'common' => ['ascension' => 3, 'level' => 100],
        'legendary' => ['ascension' => 9, 'level' => 160],
        'ascended' => ['ascension' => 12, 'level' => 240],
    ];

    public function forUserHero(UserHero $userHero, ?Hero $hero = null): float
    {
        $base = $userHero->ascension_value * 100
            + $userHero->level
            + $userHero->signature_item * 5
            + $userHero->furniture * 30
            + $userHero->engraving * 0.5;

        // Bônus de Mythic Furniture: F3 desbloqueia uma habilidade transformadora,
        // F9 melhora pra versão mais forte. Reflete que esses unlocks valem
        // muito mais do que apenas o stat boost dos 30 power/peça.
        if ($userHero->furniture >= 9) {
            $base += 200; // F9 unlock (cumulativo com F3)
        }
        if ($userHero->furniture >= 3) {
            $base += 100; // F3 unlock
        }

        $base += $this->statContribution($hero?->stats);

        return $base;
    }

    public function targetBaseline(Hero $hero): float
    {
        $tierCode = $hero->tier?->code ?? 'common';
        $baseline = self::TIER_BASELINE[$tierCode] ?? self::TIER_BASELINE['common'];

        return $baseline['ascension'] * 100 + $baseline['level'] + $this->statContribution($hero->stats ?? null);
    }

    public function forHero(Hero $hero, ?UserHero $userHero): float
    {
        return $userHero
            ? $this->forUserHero($userHero, $hero)
            : $this->targetBaseline($hero);
    }

    /**
     * Stats base do herói (Nv 1) viram um diferenciador entre heróis no scoring.
     * HP/ATK/DEF têm peso linear; demais (crit/acc/dodge/haste) entram com peso menor.
     * Quando stats não foram cadastradas, retorna 0.0 (não penaliza, mas também não diferencia).
     */
    public function statContribution(?HeroStat $stats): float
    {
        if ($stats === null) {
            return 0.0;
        }

        return ($stats->hp ?? 0) * 0.1
            + ($stats->attack ?? 0) * 1.0
            + ($stats->defense ?? 0) * 0.7
            + ($stats->crit_rate ?? 0) * 0.3
            + ($stats->crit_amp ?? 0) * 0.3
            + ($stats->accuracy ?? 0) * 0.2
            + ($stats->dodge ?? 0) * 0.2
            + ($stats->haste ?? 0) * 0.2;
    }
}
