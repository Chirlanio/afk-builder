<?php

namespace App\Services\Optimization;

class ScoreBreakdown
{
    public function __construct(
        public readonly float $factionBonus,
        public readonly float $classBalance,
        public readonly float $synergy,
        public readonly float $power,
        public readonly float $total,
        public readonly array $details = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'faction_bonus' => round($this->factionBonus, 3),
            'class_balance' => round($this->classBalance, 3),
            'synergy' => round($this->synergy, 3),
            'power' => round($this->power, 3),
            'total' => round($this->total, 3),
            'details' => $this->details,
        ];
    }
}
