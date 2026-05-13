<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignatureItemCost extends Model
{
    public const EMBLEM_PRIMORDIAL = 'primordial';

    public const EMBLEM_AMPLIFYING = 'amplifying';

    public const EMBLEM_FACTION = 'faction';

    /** Facções que conseguem ir até SI +40. Demais param em +30. */
    public const FACTION_CAP_40 = ['celestial', 'hypogean'];

    public const SI_CAP_DEFAULT = 30;

    public const SI_CAP_EXTENDED = 40;

    protected $primaryKey = 'level';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['level', 'cost', 'emblem_type'];

    protected $casts = [
        'level' => 'integer',
        'cost' => 'integer',
    ];

    public static function capForFaction(?string $factionCode): int
    {
        return in_array($factionCode, self::FACTION_CAP_40, true)
            ? self::SI_CAP_EXTENDED
            : self::SI_CAP_DEFAULT;
    }

    /**
     * Sumariza o investimento de SI para um hero/userHero.
     *
     * @param  int  $current  Nível atual de SI (0-40)
     * @param  string|null  $factionCode  Para definir cap (30 ou 40)
     * @return array{
     *     current: int,
     *     cap: int,
     *     next_level: ?int,
     *     next_cost: ?int,
     *     next_emblem_type: ?string,
     *     cumulative_to_cap: ?int,
     *     levels_remaining: int,
     *     is_capped: bool,
     * }
     */
    public static function summary(int $current, ?string $factionCode = null): array
    {
        $cap = self::capForFaction($factionCode);
        $current = max(0, min($current, $cap));
        $isCapped = $current >= $cap;

        if ($isCapped) {
            return [
                'current' => $current,
                'cap' => $cap,
                'next_level' => null,
                'next_cost' => null,
                'next_emblem_type' => null,
                'cumulative_to_cap' => 0,
                'levels_remaining' => 0,
                'is_capped' => true,
            ];
        }

        $next = self::query()->where('level', $current + 1)->first();

        $cumulative = (int) self::query()
            ->whereBetween('level', [$current + 1, $cap])
            ->sum('cost');

        return [
            'current' => $current,
            'cap' => $cap,
            'next_level' => $current + 1,
            'next_cost' => $next?->cost,
            'next_emblem_type' => $next?->emblem_type,
            'cumulative_to_cap' => $cumulative > 0 ? $cumulative : null,
            'levels_remaining' => $cap - $current,
            'is_capped' => false,
        ];
    }
}
