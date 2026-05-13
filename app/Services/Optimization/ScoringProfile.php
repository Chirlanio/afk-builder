<?php

namespace App\Services\Optimization;

class ScoringProfile
{
    /**
     * Pesos default por tag de furniture effect. Ajustados por challenge type via
     * scoring_profile.furniture_effect_tag_weights (merge sobre estes defaults).
     *
     * @var array<string, float>
     */
    public const DEFAULT_FURNITURE_EFFECT_TAG_WEIGHTS = [
        FurnitureEffectClassifier::TAG_DAMAGE_BOOST => 1.0,
        FurnitureEffectClassifier::TAG_DAMAGE_REDUCTION => 0.7,
        FurnitureEffectClassifier::TAG_HEALING => 0.7,
        FurnitureEffectClassifier::TAG_CC_CONTROL => 0.8,
        FurnitureEffectClassifier::TAG_CC_IMMUNITY => 0.8,
        FurnitureEffectClassifier::TAG_DISPEL => 0.6,
        FurnitureEffectClassifier::TAG_REVIVE => 0.7,
        FurnitureEffectClassifier::TAG_ENERGY_BOOST => 0.6,
        FurnitureEffectClassifier::TAG_SHIELD => 0.7,
        FurnitureEffectClassifier::TAG_SURVIVABILITY => 0.6,
        FurnitureEffectClassifier::TAG_ABILITY_UPTIME => 0.8,
    ];

    /**
     * @param  array<string, float>  $furnitureEffectTagWeights  Pesos por tag — merge sobre os defaults.
     */
    public function __construct(
        public readonly float $factionBonusWeight = 1.0,
        public readonly float $classBalanceWeight = 1.0,
        public readonly float $synergyWeight = 1.0,
        public readonly float $powerWeight = 1.0,
        public readonly float $tierRatingWeight = 1.0,
        public readonly float $featureWeight = 0.5,
        public readonly float $furnitureEffectWeight = 0.4,
        public readonly array $furnitureEffectTagWeights = [],
        public readonly bool $pvp = false,
        public readonly bool $dpsFocus = false,
        public readonly bool $partitionOptimization = false,
    ) {}

    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            factionBonusWeight: (float) ($data['faction_bonus_weight'] ?? 1.0),
            classBalanceWeight: (float) ($data['class_balance_weight'] ?? 1.0),
            synergyWeight: (float) ($data['synergy_weight'] ?? 1.0),
            powerWeight: (float) ($data['power_weight'] ?? 1.0),
            tierRatingWeight: (float) ($data['tier_rating_weight'] ?? 1.0),
            featureWeight: (float) ($data['feature_weight'] ?? 0.5),
            furnitureEffectWeight: (float) ($data['furniture_effect_weight'] ?? 0.4),
            furnitureEffectTagWeights: is_array($data['furniture_effect_tag_weights'] ?? null)
                ? $data['furniture_effect_tag_weights']
                : [],
            pvp: (bool) ($data['pvp'] ?? false),
            dpsFocus: (bool) ($data['dps_focus'] ?? false),
            partitionOptimization: (bool) ($data['partition_optimization'] ?? false),
        );
    }

    /**
     * Merge dos tag weights do profile sobre os defaults.
     *
     * @return array<string, float>
     */
    public function effectiveFurnitureTagWeights(): array
    {
        return array_merge(self::DEFAULT_FURNITURE_EFFECT_TAG_WEIGHTS, $this->furnitureEffectTagWeights);
    }
}
