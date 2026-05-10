<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\HeroFeature;
use App\Models\HeroRating;
use Illuminate\Support\Collection;

/**
 * Bônus contextual baseado nas tags de HeroFeature do dump afk.guide.
 *
 * Regra:
 *  - Mode PvP → +0.15 por herói com `pvp_top`
 *  - Mode Twisted Realm → +0.15 por herói com `twisted_realm_top`
 *  - Stage early (qualquer modo) → +0.05 por herói com qualquer tag `early_*`
 *
 * Bônus se acumulam (um herói early_carry + pvp_top em comp PvP early ganha os dois).
 * Score retornado é o somatório, sem normalizar — TeamScorer já aplica `featureWeight`.
 */
class FeatureBonusCalculator
{
    private const PVP_TOP_BONUS = 0.15;
    private const TWISTED_TOP_BONUS = 0.15;
    private const EARLY_TAG_BONUS = 0.05;

    /**
     * @param  Hero[]  $heroes
     * @param  array<int, Collection<int, HeroFeature>>  $featuresByHeroId
     */
    public function score(array $heroes, array $featuresByHeroId, string $mode, string $stage): float
    {
        $score = 0.0;

        foreach ($heroes as $hero) {
            $features = $featuresByHeroId[$hero->id] ?? null;
            if (! $features || $features->isEmpty()) {
                continue;
            }

            if ($mode === HeroRating::MODE_PVP && $features->contains('tag', HeroFeature::TAG_PVP_TOP)) {
                $score += self::PVP_TOP_BONUS;
            }

            if ($mode === HeroRating::MODE_TWISTED_REALM && $features->contains('tag', HeroFeature::TAG_TWISTED_REALM_TOP)) {
                $score += self::TWISTED_TOP_BONUS;
            }

            if ($stage === HeroRating::STAGE_EARLY) {
                $hasEarlyTag = $features->contains(fn (HeroFeature $f) => str_starts_with($f->tag, 'early_'));
                if ($hasEarlyTag) {
                    $score += self::EARLY_TAG_BONUS;
                }
            }
        }

        return $score;
    }
}
