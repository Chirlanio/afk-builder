<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\HeroFurnitureAbility;
use App\Models\UserHero;

class FurnitureEffectScorer
{
    /**
     * Pontua a contribuição de furniture effects desbloqueados pelo time.
     *
     * - Modo real (com UserHero): F3 ativo quando userHero.furniture >= 3, F9 >= 9.
     * - Modo target (sem UserHero): assume F9 unlocked se o hero é Ascended,
     *   F3 se Legendary+ (proxy razoável — endgame players costumam ter F9 nos
     *   carries principais; o objetivo é diferenciar comps que exploram bem
     *   o roster ideal mais que o atual).
     *
     * Cada tag em F3/F9 contribui com `tagWeight` × `unlockMultiplier`, onde
     * F9 vale mais que F3 (1.0 vs 0.5) — reflete que F9 é o upgrade definitivo.
     *
     * @param  Hero[]  $heroes
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<int, HeroFurnitureAbility>  $abilitiesByHeroId
     */
    public function score(
        array $heroes,
        array $userHeroesById,
        array $abilitiesByHeroId,
        ScoringProfile $profile,
    ): float {
        if (empty($abilitiesByHeroId)) {
            return 0.0;
        }

        $tagWeights = $profile->effectiveFurnitureTagWeights();
        $total = 0.0;

        foreach ($heroes as $hero) {
            $ability = $abilitiesByHeroId[$hero->id] ?? null;
            if ($ability === null || empty($ability->effects)) {
                continue;
            }

            [$f3Active, $f9Active] = $this->activeUnlocks($hero, $userHeroesById[$hero->id] ?? null);

            if ($f3Active && isset($ability->effects['f3'])) {
                foreach ($ability->effects['f3'] as $tag) {
                    $total += ($tagWeights[$tag] ?? 0.0) * 0.5;
                }
            }
            if ($f9Active && isset($ability->effects['f9'])) {
                foreach ($ability->effects['f9'] as $tag) {
                    $total += $tagWeights[$tag] ?? 0.0;
                }
            }
        }

        return $total;
    }

    /**
     * @return array{0: bool, 1: bool} [f3Active, f9Active]
     */
    private function activeUnlocks(Hero $hero, ?UserHero $userHero): array
    {
        if ($userHero !== null) {
            return [$userHero->furniture >= 3, $userHero->furniture >= 9];
        }

        // Modo target: usa tier do hero como proxy.
        $tierCode = $hero->tier?->code;

        return match ($tierCode) {
            'ascended' => [true, true],
            'legendary' => [true, false],
            default => [false, false],
        };
    }
}
