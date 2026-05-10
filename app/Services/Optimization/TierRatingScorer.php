<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Models\HeroRating;
use Illuminate\Support\Collection;

/**
 * Pontua um time pelo tier rating dos heróis no modo+estágio do desafio.
 *
 * Estratégia de fonte:
 *  - Prefere afk.guide (tem stages early/mid/end e múltiplos modos)
 *  - Fallback para allclash (tier list único: stage='all', mode='overall')
 *  - Sem rating → score 0 (não diferencia, mas também não penaliza)
 *
 * Conversão tier → ponto:
 *   S+ = 10, S = 8, A+ = 7, A = 6, B+ = 5, B = 4, C+ = 3, C = 2, D+ = 1, D = 0.5, F = 0
 *
 * O score final do time é a média dos pontos dividida por 10 (faixa 0..1).
 */
class TierRatingScorer
{
    private const TIER_POINTS = [
        'S+' => 10.0,
        'S' => 8.0,
        'A+' => 7.0,
        'A' => 6.0,
        'B+' => 5.0,
        'B' => 4.0,
        'C+' => 3.0,
        'C' => 2.0,
        'D+' => 1.0,
        'D' => 0.5,
        'F' => 0.0,
    ];

    /**
     * @param  Hero[]  $heroes
     * @param  array<int, Collection<int, HeroRating>>  $ratingsByHeroId  hero_id → Collection de HeroRating
     */
    public function score(array $heroes, array $ratingsByHeroId, string $mode, string $stage): float
    {
        if (count($heroes) === 0) {
            return 0.0;
        }

        $totalPoints = 0.0;
        foreach ($heroes as $hero) {
            $totalPoints += $this->pointsForHero($hero->id, $ratingsByHeroId, $mode, $stage);
        }

        $avg = $totalPoints / count($heroes);
        return $avg / 10.0;
    }

    /**
     * Retorna pontos brutos (0..10) de um herói pra um modo+estágio.
     *
     * @param  array<int, Collection<int, HeroRating>>  $ratingsByHeroId
     */
    public function pointsForHero(int $heroId, array $ratingsByHeroId, string $mode, string $stage): float
    {
        $ratings = $ratingsByHeroId[$heroId] ?? null;
        if (! $ratings || $ratings->isEmpty()) {
            return 0.0;
        }

        // 1ª tentativa: afk.guide com modo + estágio exatos
        $rating = $ratings->first(
            fn (HeroRating $r) => $r->source === 'afk.guide' && $r->mode === $mode && $r->stage === $stage,
        );
        if ($rating) {
            return self::TIER_POINTS[$rating->tier] ?? 0.0;
        }

        // 2ª: afk.guide com mesmo modo, qualquer estágio (preferência: end > mid > early)
        $rating = $ratings
            ->filter(fn (HeroRating $r) => $r->source === 'afk.guide' && $r->mode === $mode)
            ->sortBy(fn (HeroRating $r) => match ($r->stage) {
                HeroRating::STAGE_END => 0,
                HeroRating::STAGE_MID => 1,
                HeroRating::STAGE_EARLY => 2,
                default => 3,
            })
            ->first();
        if ($rating) {
            return self::TIER_POINTS[$rating->tier] ?? 0.0;
        }

        // 3ª: afk.guide overall qualquer estágio
        $rating = $ratings->first(fn (HeroRating $r) => $r->source === 'afk.guide' && $r->mode === HeroRating::MODE_OVERALL);
        if ($rating) {
            return self::TIER_POINTS[$rating->tier] ?? 0.0;
        }

        // 4ª: allclash (overall/all)
        $rating = $ratings->first(fn (HeroRating $r) => $r->source === 'allclash');
        if ($rating) {
            return self::TIER_POINTS[$rating->tier] ?? 0.0;
        }

        return 0.0;
    }
}
