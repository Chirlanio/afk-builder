<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\HeroRating;
use App\Models\RecommendedFormation;

/**
 * Compara um time atual com os comps recomendados curados (afk.guide via Fase 21.4)
 * e retorna os matches com maior overlap de heróis. Não afeta scoring; é
 * informação para a UI sugerir "essa equipe se aproxima do Thoran Cheese".
 */
class RecommendedFormationMatcher
{
    /**
     * Mode (HeroRating::MODE_*) → modes do RecommendedFormation. Mais de um
     * mode pode ser relevante (ex: PVE engloba campaign+labyrinth+abyssal).
     */
    private const MODE_TO_RF_MODES = [
        HeroRating::MODE_PVP => [RecommendedFormation::MODE_PVP],
        HeroRating::MODE_PVE => [RecommendedFormation::MODE_PVE],
        HeroRating::MODE_LABYRINTH => [RecommendedFormation::MODE_LABYRINTH, RecommendedFormation::MODE_ARCANE_LABYRINTH, RecommendedFormation::MODE_PVE],
        HeroRating::MODE_TWISTED_REALM => [RecommendedFormation::MODE_TWISTED_REALM, RecommendedFormation::MODE_PVE],
        HeroRating::MODE_ABYSSAL_EXPEDITION => [RecommendedFormation::MODE_PVE],
        HeroRating::MODE_DRAGONFORGE => [RecommendedFormation::MODE_DRAGONFORGE],
    ];

    /**
     * @param  Hero[]  $heroes  heróis da equipe atual (com slug)
     * @param  string  $mode  HeroRating::MODE_* derivado do challenge_type
     * @param  int  $minOverlap  mínimo de heróis em comum para considerar match
     * @return array<int, array{slug: string, name: string, mode: string, tier: ?string, core_overlap: int, total_overlap: int, total_core: int, percent: float, missing_core: array<string>}>
     */
    public function match(array $heroes, string $mode, int $minOverlap = 2, int $limit = 3): array
    {
        $heroSlugs = array_filter(array_map(fn (Hero $h) => $h->slug, $heroes));
        if (empty($heroSlugs)) {
            return [];
        }
        $heroSlugSet = array_flip($heroSlugs);

        $rfModes = self::MODE_TO_RF_MODES[$mode] ?? [RecommendedFormation::MODE_PVE];
        $recommended = RecommendedFormation::query()
            ->where('is_active', true)
            ->whereIn('mode', $rfModes)
            ->orderBy('sort_order')
            ->get();

        $matches = [];
        foreach ($recommended as $rf) {
            $rfSlugs = $rf->hero_slugs ?? [];
            if (empty($rfSlugs)) {
                continue;
            }

            // Comp de várias equipes: o time do editor é comparado com cada equipe
            // (as 5 dela são o "core") e fica a mais parecida.
            $candidates = ! empty($rf->teams)
                ? array_map(fn (array $team, int $i) => ['label' => ' · Equipe '.($i + 1), 'core' => $team, 'all' => $team], $rf->teams, array_keys($rf->teams))
                : [['label' => '', 'core' => array_slice($rfSlugs, 0, max(0, $rf->core_count)), 'all' => $rfSlugs]];

            $best = null;
            foreach ($candidates as $candidate) {
                $allOverlap = count(array_filter($candidate['all'], fn (string $slug) => isset($heroSlugSet[$slug])));
                $coreOverlap = count(array_filter($candidate['core'], fn (string $slug) => isset($heroSlugSet[$slug])));
                if ($best === null || [$coreOverlap, $allOverlap] > [$best['core_overlap'], $best['total_overlap']]) {
                    $best = ['candidate' => $candidate, 'core_overlap' => $coreOverlap, 'total_overlap' => $allOverlap];
                }
            }

            if ($best['total_overlap'] < $minOverlap) {
                continue;
            }

            $coreSlugs = $best['candidate']['core'];
            $missingCore = array_values(array_filter(
                $coreSlugs,
                fn (string $slug) => ! isset($heroSlugSet[$slug]),
            ));

            $matches[] = [
                'slug' => $rf->slug,
                'name' => $rf->name.$best['candidate']['label'],
                'mode' => $rf->mode,
                'tier' => $rf->tier,
                'core_overlap' => $best['core_overlap'],
                'total_overlap' => $best['total_overlap'],
                'total_core' => count($coreSlugs),
                'percent' => count($coreSlugs) > 0 ? round($best['core_overlap'] / count($coreSlugs), 2) : 0.0,
                'missing_core' => $missingCore,
            ];
        }

        // Ordena por overlap de core desc, depois total overlap desc
        usort($matches, function (array $a, array $b) {
            if ($a['core_overlap'] !== $b['core_overlap']) {
                return $b['core_overlap'] - $a['core_overlap'];
            }

            return $b['total_overlap'] - $a['total_overlap'];
        });

        return array_slice($matches, 0, $limit);
    }
}
