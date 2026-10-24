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
        $rating = $this->ratingForHero($heroId, $ratingsByHeroId, $mode, $stage);

        return $rating ? (self::TIER_POINTS[$rating->tier] ?? 0.0) : 0.0;
    }

    /**
     * O rating que a pontuação usa para o herói num Contexto+estágio: o do
     * próprio Contexto e, na falta dele, o geral (afk.guide e depois allclash).
     *
     * @param  array<int, Collection<int, HeroRating>>  $ratingsByHeroId
     */
    public function ratingForHero(int $heroId, array $ratingsByHeroId, string $mode, string $stage): ?HeroRating
    {
        $rating = $this->contextRatingForHero($heroId, $ratingsByHeroId, $mode, $stage);
        if ($rating) {
            return $rating;
        }

        $ratings = $ratingsByHeroId[$heroId] ?? null;
        if (! $ratings) {
            return null;
        }

        // 3ª: afk.guide overall qualquer estágio
        $rating = $ratings->first(fn (HeroRating $r) => $r->source === 'afk.guide' && $r->mode === HeroRating::MODE_OVERALL);
        if ($rating) {
            return $rating;
        }

        // 4ª: allclash (overall/all)
        return $ratings->first(fn (HeroRating $r) => $r->source === 'allclash');
    }

    /**
     * Só o rating afk.guide do próprio Contexto (estágio exato ou o mais
     * próximo do fim de jogo); null quando o Contexto não avalia o herói.
     *
     * @param  array<int, Collection<int, HeroRating>>  $ratingsByHeroId
     */
    public function contextRatingForHero(int $heroId, array $ratingsByHeroId, string $mode, string $stage): ?HeroRating
    {
        $ratings = $ratingsByHeroId[$heroId] ?? null;
        if (! $ratings || $ratings->isEmpty()) {
            return null;
        }

        // 1ª tentativa: afk.guide com modo + estágio exatos
        $rating = $ratings->first(
            fn (HeroRating $r) => $r->source === 'afk.guide' && $r->mode === $mode && $r->stage === $stage,
        );
        if ($rating) {
            return $rating;
        }

        // 2ª: afk.guide com mesmo modo, qualquer estágio (preferência: end > mid > early)
        return $ratings
            ->filter(fn (HeroRating $r) => $r->source === 'afk.guide' && $r->mode === $mode)
            ->sortBy(fn (HeroRating $r) => match ($r->stage) {
                HeroRating::STAGE_END => 0,
                HeroRating::STAGE_MID => 1,
                HeroRating::STAGE_EARLY => 2,
                default => 3,
            })
            ->first();
    }
}
