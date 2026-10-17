<?php

namespace App\Services\Optimization;

use App\Models\Beast;
use App\Models\Hero;
use App\Models\UserHero;

class SingleTeamOptimizer
{
    private const TEAM_SIZE = 5;

    private const HILL_CLIMB_ITERATIONS = 100;

    public function __construct(private readonly TeamScorer $scorer) {}

    /**
     * @param  array<int, Hero>  $heroPoolById
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<string, int>  $synergyWeights  ver TeamScorer::pairKey
     * @param  int[]  $lockedHeroIds  heróis já posicionados na equipe-alvo (não mexe)
     * @param  int[]  $blockedHeroIds  heróis proibidos (em outras equipes com unique=true)
     * @return array{hero_ids: int[], score: ScoreBreakdown}
     */
    public function optimize(
        array $heroPoolById,
        array $userHeroesById,
        array $synergyWeights,
        ScoringProfile $profile,
        array $lockedHeroIds,
        array $blockedHeroIds,
        ?Beast $beast = null,
        ?ScoringContext $ctx = null,
    ): array {
        $emptyCount = self::TEAM_SIZE - count($lockedHeroIds);
        $lockedHeroes = $this->resolveHeroes($lockedHeroIds, $heroPoolById);

        if ($emptyCount <= 0) {
            return [
                'hero_ids' => $lockedHeroIds,
                'score' => $this->scorer->score($lockedHeroes, $userHeroesById, $synergyWeights, $profile, $beast, $ctx),
            ];
        }

        $excluded = array_flip(array_merge($lockedHeroIds, $blockedHeroIds));
        $candidates = [];
        foreach ($heroPoolById as $id => $hero) {
            if (! isset($excluded[$id])) {
                $candidates[] = $hero;
            }
        }

        if (count($candidates) === 0) {
            return [
                'hero_ids' => $lockedHeroIds,
                'score' => $this->scorer->score($lockedHeroes, $userHeroesById, $synergyWeights, $profile, $beast, $ctx),
            ];
        }

        $emptyCount = min($emptyCount, count($candidates));

        if ($emptyCount <= 2) {
            return $this->exhaustive($lockedHeroes, $candidates, $emptyCount, $userHeroesById, $synergyWeights, $profile, $beast, $ctx);
        }

        return $this->greedyWithHillClimb($lockedHeroes, $candidates, $emptyCount, $userHeroesById, $synergyWeights, $profile, $beast, $ctx);
    }

    /**
     * @param  Hero[]  $locked
     * @param  Hero[]  $candidates
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<string, int>  $synergyWeights
     * @return array{hero_ids: int[], score: ScoreBreakdown}
     */
    private function exhaustive(
        array $locked,
        array $candidates,
        int $emptyCount,
        array $userHeroesById,
        array $synergyWeights,
        ScoringProfile $profile,
        ?Beast $beast = null,
        ?ScoringContext $ctx = null,
    ): array {
        $bestScore = null;
        $bestHeroes = [];
        $n = count($candidates);

        if ($emptyCount === 1) {
            for ($i = 0; $i < $n; $i++) {
                $team = array_merge($locked, [$candidates[$i]]);
                $score = $this->scorer->score($team, $userHeroesById, $synergyWeights, $profile, $beast, $ctx);
                if ($bestScore === null || $score->total > $bestScore->total) {
                    $bestScore = $score;
                    $bestHeroes = $team;
                }
            }
        } else {
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $team = array_merge($locked, [$candidates[$i], $candidates[$j]]);
                    $score = $this->scorer->score($team, $userHeroesById, $synergyWeights, $profile, $beast, $ctx);
                    if ($bestScore === null || $score->total > $bestScore->total) {
                        $bestScore = $score;
                        $bestHeroes = $team;
                    }
                }
            }
        }

        return [
            'hero_ids' => array_map(fn (Hero $h) => $h->id, $bestHeroes),
            'score' => $bestScore,
        ];
    }

    /**
     * Greedy (escolhe o melhor herói por vez) seguido de hill climb (swaps locais).
     *
     * @param  Hero[]  $locked
     * @param  Hero[]  $candidates
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<string, int>  $synergyWeights
     * @return array{hero_ids: int[], score: ScoreBreakdown}
     */
    private function greedyWithHillClimb(
        array $locked,
        array $candidates,
        int $emptyCount,
        array $userHeroesById,
        array $synergyWeights,
        ScoringProfile $profile,
        ?Beast $beast = null,
        ?ScoringContext $ctx = null,
    ): array {
        $team = $locked;
        $usedIds = array_flip(array_map(fn (Hero $h) => $h->id, $locked));

        for ($pick = 0; $pick < $emptyCount; $pick++) {
            $bestScore = null;
            $bestHero = null;

            foreach ($candidates as $candidate) {
                if (isset($usedIds[$candidate->id])) {
                    continue;
                }
                $score = $this->scorer->score(
                    array_merge($team, [$candidate]),
                    $userHeroesById,
                    $synergyWeights,
                    $profile,
                    $beast,
                    $ctx,
                );
                if ($bestScore === null || $score->total > $bestScore->total) {
                    $bestScore = $score;
                    $bestHero = $candidate;
                }
            }

            if ($bestHero === null) {
                break;
            }

            $team[] = $bestHero;
            $usedIds[$bestHero->id] = true;
        }

        $currentScore = $this->scorer->score($team, $userHeroesById, $synergyWeights, $profile, $beast, $ctx);
        $lockedCount = count($locked);

        for ($iter = 0; $iter < self::HILL_CLIMB_ITERATIONS; $iter++) {
            $improved = false;

            for ($i = $lockedCount; $i < count($team); $i++) {
                foreach ($candidates as $candidate) {
                    if (isset($usedIds[$candidate->id])) {
                        continue;
                    }

                    $oldHero = $team[$i];
                    $team[$i] = $candidate;
                    $newScore = $this->scorer->score($team, $userHeroesById, $synergyWeights, $profile, $beast, $ctx);

                    if ($newScore->total > $currentScore->total) {
                        unset($usedIds[$oldHero->id]);
                        $usedIds[$candidate->id] = true;
                        $currentScore = $newScore;
                        $improved = true;
                        break;
                    }

                    $team[$i] = $oldHero;
                }

                if ($improved) {
                    break;
                }
            }

            if (! $improved) {
                break;
            }
        }

        return [
            'hero_ids' => array_map(fn (Hero $h) => $h->id, $team),
            'score' => $currentScore,
        ];
    }

    /**
     * @param  int[]  $ids
     * @param  array<int, Hero>  $heroPoolById
     * @return Hero[]
     */
    private function resolveHeroes(array $ids, array $heroPoolById): array
    {
        $resolved = [];
        foreach ($ids as $id) {
            if (isset($heroPoolById[$id])) {
                $resolved[] = $heroPoolById[$id];
            }
        }

        return $resolved;
    }
}
