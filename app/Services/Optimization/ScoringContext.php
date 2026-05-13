<?php

namespace App\Services\Optimization;

use App\Models\HeroFeature;
use App\Models\HeroFurnitureAbility;
use App\Models\HeroRating;
use Illuminate\Support\Collection;

/**
 * Bundle de dados pré-carregados que viajam pelo loop de scoring sem
 * recalcular. Usado pelo TierRatingScorer, FeatureBonusCalculator e
 * FurnitureEffectScorer.
 *
 * Quando construído por defaults, todos os componentes contextuais retornam
 * 0.0 e o scoring se comporta como nas Fases 1-23.
 */
class ScoringContext
{
    /**
     * @param  array<int, Collection<int, HeroRating>>  $ratingsByHeroId
     * @param  array<int, Collection<int, HeroFeature>>  $featuresByHeroId
     * @param  array<int, HeroFurnitureAbility>  $furnitureAbilitiesByHeroId
     */
    public function __construct(
        public readonly array $ratingsByHeroId = [],
        public readonly array $featuresByHeroId = [],
        public readonly array $furnitureAbilitiesByHeroId = [],
        public readonly string $mode = HeroRating::MODE_PVE,
        public readonly string $stage = HeroRating::STAGE_END,
    ) {}
}
