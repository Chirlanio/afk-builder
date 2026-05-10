<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\HeroRating;
use App\Models\RecommendedFormation;
use Illuminate\Support\Collection;

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

            $coreSlugs = array_slice($rfSlugs, 0, max(0, $rf->core_count));
            $allOverlap = 0;
            $coreOverlap = 0;
            foreach ($rfSlugs as $idx => $slug) {
                if (isset($heroSlugSet[$slug])) {
                    $allOverlap++;
                    if ($idx < $rf->core_count) {
                        $coreOverlap++;
                    }
                }
            }

            if ($allOverlap < $minOverlap) {
                continue;
            }

            $missingCore = array_values(array_filter(
                $coreSlugs,
                fn (string $slug) => ! isset($heroSlugSet[$slug]),
            ));

            $matches[] = [
                'slug' => $rf->slug,
                'name' => $rf->name,
                'mode' => $rf->mode,
                'tier' => $rf->tier,
                'core_overlap' => $coreOverlap,
                'total_overlap' => $allOverlap,
                'total_core' => (int) $rf->core_count,
                'percent' => $rf->core_count > 0 ? round($coreOverlap / $rf->core_count, 2) : 0.0,
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
