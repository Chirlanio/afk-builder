<?php

namespace App\Services\Optimization;

class ScoringProfile
{
    public function __construct(
        public readonly float $factionBonusWeight = 1.0,
        public readonly float $classBalanceWeight = 1.0,
        public readonly float $synergyWeight = 1.0,
        public readonly float $powerWeight = 1.0,
        public readonly bool $pvp = false,
        public readonly bool $dpsFocus = false,
        public readonly bool $partitionOptimization = false,
    ) {
    }

    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            factionBonusWeight: (float) ($data['faction_bonus_weight'] ?? 1.0),
            classBalanceWeight: (float) ($data['class_balance_weight'] ?? 1.0),
            synergyWeight: (float) ($data['synergy_weight'] ?? 1.0),
            powerWeight: (float) ($data['power_weight'] ?? 1.0),
            pvp: (bool) ($data['pvp'] ?? false),
            dpsFocus: (bool) ($data['dps_focus'] ?? false),
            partitionOptimization: (bool) ($data['partition_optimization'] ?? false),
        );
    }
}
