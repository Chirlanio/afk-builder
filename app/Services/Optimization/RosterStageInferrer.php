<?php

namespace App\Services\Optimization;

use App\Models\HeroRating;
use App\Models\UserHero;

/**
 * Infere o estágio (early/mid/end) do roster do user a partir da
 * média de ascension_value dos heróis cadastrados. Usado pelo
 * TierRatingScorer e pelo FeatureBonus para escolher qual rating/feature
 * é relevante naquele contexto.
 *
 * Cortes alinhados ao tierFromAscension do frontend (lib/afk.ts):
 *  - 0..3   → common (early)
 *  - 4..9   → legendary (mid)
 *  - 10+    → ascended (end)
 */
class RosterStageInferrer
{
    public const STAGE_EARLY_THRESHOLD = 5.0;
    public const STAGE_MID_THRESHOLD = 10.0;

    /**
     * @param  iterable<UserHero>  $userHeroes
     */
    public function inferFromRoster(iterable $userHeroes): string
    {
        $sum = 0;
        $count = 0;
        foreach ($userHeroes as $userHero) {
            $sum += (int) ($userHero->ascension_value ?? 0);
            $count++;
        }

        if ($count === 0) {
            return HeroRating::STAGE_END;
        }

        $avg = $sum / $count;
        if ($avg < self::STAGE_EARLY_THRESHOLD) {
            return HeroRating::STAGE_EARLY;
        }
        if ($avg < self::STAGE_MID_THRESHOLD) {
            return HeroRating::STAGE_MID;
        }
        return HeroRating::STAGE_END;
    }
}
