<?php

namespace App\Services\Optimization;

use App\Models\Beast;
use App\Models\Hero;
use App\Models\UserHero;

class MultiTeamPartitioner
{
    private const SWAP_PASSES = 50;

    public function __construct(
        private readonly TeamScorer $scorer,
        private readonly SingleTeamOptimizer $singleOptimizer,
    ) {
    }

    /**
     * @param  array<int, Hero>  $heroPoolById
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<string, int>  $synergyWeights
     * @param  array<int, int[]>  $lockedByTeam  position => [hero_id, ...] já fixos
     * @return array{teams: array<int, int[]>, total_score: float}
     */
    public function optimize(
        array $heroPoolById,
        array $userHeroesById,
        array $synergyWeights,
        ScoringProfile $profile,
        array $lockedByTeam,
        int $teamsCount,
        bool $enforceUnique,
        ?Beast $beast = null,
        ?ScoringContext $ctx = null,
    ): array {
        $teams = $this->initialFill(
            $heroPoolById, $userHeroesById, $synergyWeights, $profile,
            $lockedByTeam, $teamsCount, $enforceUnique, $beast, $ctx,
        );

        if ($enforceUnique && $teamsCount > 1) {
            $teams = $this->swapOptimize(
                $teams, $heroPoolById, $userHeroesById, $synergyWeights, $profile, $lockedByTeam, $beast, $ctx,
            );
        }

        $totalScore = 0.0;
        foreach ($teams as $heroIds) {
            $heroes = $this->resolveHeroes($heroIds, $heroPoolById);
            $totalScore += $this->scorer->score($heroes, $userHeroesById, $synergyWeights, $profile, $beast, $ctx)->total;
        }

        return [
            'teams' => $teams,
            'total_score' => $totalScore,
        ];
    }

    /**
     * @param  array<int, Hero>  $heroPoolById
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<string, int>  $synergyWeights
     * @param  array<int, int[]>  $lockedByTeam
     * @return array<int, int[]>
     */
    private function initialFill(
        array $heroPoolById,
        array $userHeroesById,
        array $synergyWeights,
        ScoringProfile $profile,
        array $lockedByTeam,
        int $teamsCount,
        bool $enforceUnique,
        ?Beast $beast = null,
        ?ScoringContext $ctx = null,
    ): array {
        $usedIds = [];
        if ($enforceUnique) {
            foreach ($lockedByTeam as $ids) {
                foreach ($ids as $id) {
                    $usedIds[$id] = true;
                }
            }
        }

        $teams = [];
        for ($pos = 1; $pos <= $teamsCount; $pos++) {
            $locked = $lockedByTeam[$pos] ?? [];
            $blocked = [];
            if ($enforceUnique) {
                $lockedFlip = array_flip($locked);
                $blocked = array_keys(array_diff_key($usedIds, $lockedFlip));
            }

            $result = $this->singleOptimizer->optimize(
                $heroPoolById, $userHeroesById, $synergyWeights, $profile,
                $locked, $blocked, $beast, $ctx,
            );

            $teams[$pos] = $result['hero_ids'];

            if ($enforceUnique) {
                foreach ($result['hero_ids'] as $id) {
                    $usedIds[$id] = true;
                }
            }
        }

        return $teams;
    }

    /**
     * Tenta trocar heróis (não-locked) entre pares de equipes; aceita se melhora a soma dos scores.
     *
     * @param  array<int, int[]>  $teams
     * @param  array<int, Hero>  $heroPoolById
     * @param  array<int, UserHero>  $userHeroesById
     * @param  array<string, int>  $synergyWeights
     * @param  array<int, int[]>  $lockedByTeam
     * @return array<int, int[]>
     */
    private function swapOptimize(
        array $teams,
        array $heroPoolById,
        array $userHeroesById,
        array $synergyWeights,
        ScoringProfile $profile,
        array $lockedByTeam,
        ?Beast $beast = null,
        ?ScoringContext $ctx = null,
    ): array {
        $positions = array_keys($teams);

        for ($pass = 0; $pass < self::SWAP_PASSES; $pass++) {
            $improved = false;

            for ($a = 0; $a < count($positions); $a++) {
                for ($b = $a + 1; $b < count($positions); $b++) {
                    $posA = $positions[$a];
                    $posB = $positions[$b];

                    $lockedA = array_flip($lockedByTeam[$posA] ?? []);
                    $lockedB = array_flip($lockedByTeam[$posB] ?? []);

                    $heroesA = $this->resolveHeroes($teams[$posA], $heroPoolById);
                    $heroesB = $this->resolveHeroes($teams[$posB], $heroPoolById);

                    $scoreA = $this->scorer->score($heroesA, $userHeroesById, $synergyWeights, $profile, $beast, $ctx)->total;
                    $scoreB = $this->scorer->score($heroesB, $userHeroesById, $synergyWeights, $profile, $beast, $ctx)->total;
                    $baseline = $scoreA + $scoreB;

                    foreach ($teams[$posA] as $iA => $idA) {
                        if (isset($lockedA[$idA])) {
                            continue;
                        }
                        foreach ($teams[$posB] as $iB => $idB) {
                            if (isset($lockedB[$idB])) {
                                continue;
                            }

                            $candidateA = $teams[$posA];
                            $candidateB = $teams[$posB];
                            $candidateA[$iA] = $idB;
                            $candidateB[$iB] = $idA;

                            $newHeroesA = $this->resolveHeroes($candidateA, $heroPoolById);
                            $newHeroesB = $this->resolveHeroes($candidateB, $heroPoolById);
                            $newSum = $this->scorer->score($newHeroesA, $userHeroesById, $synergyWeights, $profile, $beast, $ctx)->total
                                + $this->scorer->score($newHeroesB, $userHeroesById, $synergyWeights, $profile, $beast, $ctx)->total;

                            if ($newSum > $baseline + 1e-9) {
                                $teams[$posA] = $candidateA;
                                $teams[$posB] = $candidateB;
                                $baseline = $newSum;
                                $improved = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if (! $improved) {
                break;
            }
        }

        return $teams;
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
