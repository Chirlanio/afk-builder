<?php

namespace App\Services\Optimization;

use App\Models\HeroRating;

/**
 * Mapeia o `code` de ChallengeType para o `mode` do HeroRating, usado
 * pelo TierRatingScorer e pelo FeatureBonus para ponderar tiers/features
 * no contexto do desafio escolhido.
 */
class ChallengeTypeMode
{
    private const MAPPING = [
        'campaign' => HeroRating::MODE_PVE,
        'kings_tower' => HeroRating::MODE_PVE,
        'arena_of_heroes' => HeroRating::MODE_PVP,
        'legends_championship' => HeroRating::MODE_PVP,
        'heroes_of_esperia' => HeroRating::MODE_PVP,
        'dream_realm' => HeroRating::MODE_TWISTED_REALM,
        'cursed_realm' => HeroRating::MODE_PVE,
        'abyssal_expedition' => HeroRating::MODE_ABYSSAL_EXPEDITION,
        'arcane_labyrinth' => HeroRating::MODE_LABYRINTH,
        'bountiful_trials' => HeroRating::MODE_PVE,
        'dragon_trial' => HeroRating::MODE_DRAGONFORGE,
    ];

    public static function forCode(?string $code): string
    {
        if ($code === null) {
            return HeroRating::MODE_PVE;
        }

        return self::MAPPING[$code] ?? HeroRating::MODE_PVE;
    }

    public static function isPvp(?string $code): bool
    {
        return self::forCode($code) === HeroRating::MODE_PVP;
    }

    public static function isTwistedRealm(?string $code): bool
    {
        return self::forCode($code) === HeroRating::MODE_TWISTED_REALM;
    }
}
