<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\UserHero;

class TeamScorer
{
    private const POWER_NORMALIZER = 2000.0;

    public function __construct(private readonly PowerCalculator $power)
    {
    }

    /**
     * @param  Hero[]  $heroes  com faction, heroClass, tier carregados
     * @param  array<int, UserHero>  $userHeroesById  hero_id => UserHero (modo real)
     * @param  array<string, int>  $synergyWeights  chave "min-max" → weight
     */
    public function score(
        array $heroes,
        array $userHeroesById,
        array $synergyWeights,
        ScoringProfile $profile,
    ): ScoreBreakdown {
        $counts = $this->countByFaction($heroes);

        $baseFaction = $this->baseFactionBonus($counts);
        $specialFaction = $this->specialFactionBonuses($counts);
        $factionBonus = $baseFaction['score'] + $specialFaction['score'];

        $classBalance = $this->classBalance($heroes);
        $synergy = $this->synergyScore($heroes, $synergyWeights);
        $power = $this->powerScore($heroes, $userHeroesById);

        $total = $factionBonus * $profile->factionBonusWeight
            + $classBalance * $profile->classBalanceWeight
            + $synergy * $profile->synergyWeight
            + $power * $profile->powerWeight;

        return new ScoreBreakdown(
            factionBonus: $factionBonus,
            classBalance: $classBalance,
            synergy: $synergy,
            power: $power,
            total: $total,
            details: [
                'faction_counts' => $counts,
                'faction_pattern' => $baseFaction['pattern'],
                'special_faction' => $specialFaction['notes'],
            ],
        );
    }

    /**
     * @param  Hero[]  $heroes
     * @return array<string, int>
     */
    private function countByFaction(array $heroes): array
    {
        $counts = [
            'lightbearer' => 0,
            'mauler' => 0,
            'wilder' => 0,
            'graveborn' => 0,
            'celestial' => 0,
            'hypogean' => 0,
            'dimensional' => 0,
            'draconis' => 0,
        ];

        foreach ($heroes as $hero) {
            $code = $hero->faction?->code;
            if ($code !== null && array_key_exists($code, $counts)) {
                $counts[$code]++;
            }
        }

        return $counts;
    }

    /**
     * Bônus base 5-stack ou 3+2.
     * - Dimensionais não contam para stacking.
     * - Celestiais agem como wildcard para a facção dominante.
     *
     * @param  array<string, int>  $counts
     * @return array{score: float, pattern: ?string}
     */
    private function baseFactionBonus(array $counts): array
    {
        $celestials = $counts['celestial'];

        $eligible = [];
        foreach ($counts as $code => $count) {
            if ($code === 'celestial' || $code === 'dimensional') {
                continue;
            }
            if ($count > 0) {
                $eligible[$code] = $count;
            }
        }

        arsort($eligible);
        $entries = array_values(array_map(
            fn ($code, $count) => ['code' => $code, 'count' => $count],
            array_keys($eligible),
            $eligible,
        ));

        $top = $entries[0] ?? null;
        $second = $entries[1] ?? null;

        if ($top === null) {
            return ['score' => 0.0, 'pattern' => null];
        }

        if ($top['count'] + $celestials >= 5 && $top['count'] >= 1) {
            return [
                'score' => 1.0,
                'pattern' => "5x {$top['code']}" . ($celestials > 0 ? " (+{$celestials} celestial)" : ''),
            ];
        }

        if ($top['count'] >= 3) {
            $remaining = 5 - $top['count'];
            $partnerCount = $second['count'] ?? 0;
            if ($partnerCount + $celestials >= $remaining && $partnerCount >= 1) {
                $partnerCode = $second['code'];

                return [
                    'score' => 0.6,
                    'pattern' => "3+2: {$top['code']} + {$partnerCode}",
                ];
            }
            if ($celestials >= $remaining) {
                return [
                    'score' => 0.6,
                    'pattern' => "3+2: {$top['code']} + celestial",
                ];
            }
        }

        if ($top['count'] === 2 && ($second['count'] ?? 0) === 2 && $celestials >= 1) {
            return [
                'score' => 0.6,
                'pattern' => "3+2: {$top['code']} + {$second['code']} (com celestial)",
            ];
        }

        return ['score' => 0.0, 'pattern' => null];
    }

    /**
     * Bônus especiais que se aplicam independentemente do 5-stack/3+2.
     *
     * @param  array<string, int>  $counts
     * @return array{score: float, notes: array<string, mixed>}
     */
    private function specialFactionBonuses(array $counts): array
    {
        $score = 0.0;
        $notes = [];

        if ($counts['hypogean'] > 0) {
            $hypoBonus = $counts['hypogean'] * 0.1;
            $score += $hypoBonus;
            $notes['hypogean_stack'] = "{$counts['hypogean']}x → +" . round($hypoBonus, 2);
        }

        if ($counts['draconis'] >= 2) {
            $score += 0.3;
            $notes['draconis_self_advantage'] = "{$counts['draconis']}x → +0.3";
        }

        return ['score' => $score, 'notes' => $notes];
    }

    /**
     * Pontua presença de tanque, suporte e variedade de classes.
     *
     * @param  Hero[]  $heroes
     */
    private function classBalance(array $heroes): float
    {
        if (count($heroes) === 0) {
            return 0.0;
        }

        $classes = [];
        foreach ($heroes as $hero) {
            $code = $hero->heroClass?->code;
            if ($code !== null) {
                $classes[$code] = true;
            }
        }

        $score = 0.0;
        if (isset($classes['tank'])) {
            $score += 0.3;
        }
        if (isset($classes['support'])) {
            $score += 0.3;
        }
        $score += count($classes) * 0.08;

        return $score;
    }

    /**
     * Soma synergias de todos os pares no time.
     *
     * @param  Hero[]  $heroes
     * @param  array<string, int>  $synergyWeights
     */
    private function synergyScore(array $heroes, array $synergyWeights): float
    {
        $score = 0.0;
        $count = count($heroes);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $key = $this->pairKey($heroes[$i]->id, $heroes[$j]->id);
                if (isset($synergyWeights[$key])) {
                    $score += $synergyWeights[$key] / 10.0;
                }
            }
        }

        return $score;
    }

    /**
     * @param  Hero[]  $heroes
     * @param  array<int, UserHero>  $userHeroesById
     */
    private function powerScore(array $heroes, array $userHeroesById): float
    {
        $total = 0.0;
        foreach ($heroes as $hero) {
            $userHero = $userHeroesById[$hero->id] ?? null;
            $total += $this->power->forHero($hero, $userHero);
        }

        return $total / self::POWER_NORMALIZER;
    }

    public static function pairKey(int $a, int $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }
}
