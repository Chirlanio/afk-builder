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
     * Cache do somatório acumulado por nível. Calculado lazy na primeira
     * chamada via 1 query (40 rows) e reutilizado pelo PowerCalculator no
     * loop de scoring.
     *
     * Levels 39-40 (cost=NULL no seeder) caem no fallback do level 38 (120),
     * mantendo a curva crescente sem inventar valor concreto.
     *
     * @var array<int, int>|null level => cumulative_cost
     */
    private static ?array $cumulativeCache = null;

    /**
     * Retorna o custo total acumulado de subir de 0 até o nível dado.
     * Level 0 retorna 0. Levels com cost=NULL usam fallback do último custo
     * conhecido (mantém a curva crescente até o cap +40).
     */
    public static function cumulativeCostUpToLevel(int $level): int
    {
        if ($level <= 0) {
            return 0;
        }
        $level = min($level, self::SI_CAP_EXTENDED);

        if (self::$cumulativeCache === null) {
            self::$cumulativeCache = self::buildCumulativeCache();
        }

        return self::$cumulativeCache[$level] ?? 0;
    }

    /**
     * @return array<int, int>
     */
    private static function buildCumulativeCache(): array
    {
        $rows = self::query()->orderBy('level')->get(['level', 'cost']);

        $cumulative = [0 => 0];
        $running = 0;
        $lastKnownCost = 0;

        foreach ($rows as $row) {
            $cost = $row->cost ?? $lastKnownCost;
            if ($row->cost !== null) {
                $lastKnownCost = $row->cost;
            }
            $running += $cost;
            $cumulative[$row->level] = $running;
        }

        return $cumulative;
    }

    /**
     * Limpa o cache estático — usado em testes que mexem no seeder.
     */
    public static function flushCumulativeCache(): void
    {
        self::$cumulativeCache = null;
    }

    /**
     * Injeta o cache cumulativo manualmente — usado em unit tests que não
     * têm acesso ao DB. Passa array level => cumulative_cost.
     *
     * @param  array<int, int>  $cache
     */
    public static function primeCumulativeCacheForTesting(array $cache): void
    {
        self::$cumulativeCache = $cache;
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
