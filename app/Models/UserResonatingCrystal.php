<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResonatingCrystal extends Model
{
    /** Nível mínimo (estado pós-upgrade do pentagrama). */
    public const MIN_LEVEL = 240;

    /** Nível máximo absoluto suportado pelos dados de custo. */
    public const MAX_LEVEL = 501;

    /** Fortalecimentos necessários por nível. */
    public const STRENGTHENS_PER_LEVEL = 10;

    /** Bônus de teto por hero Ascended (catálogo). */
    public const LEVEL_CAP_PER_ASCENDED = 5;

    /** Slots iniciais (pentagrama). */
    public const INITIAL_SLOTS = 5;

    protected $fillable = [
        'user_id',
        'level',
        'strengthen_progress',
        'slots_unlocked',
    ];

    protected $attributes = [
        'level' => self::MIN_LEVEL,
        'strengthen_progress' => 0,
        'slots_unlocked' => self::INITIAL_SLOTS,
    ];

    protected $casts = [
        'level' => 'integer',
        'strengthen_progress' => 'integer',
        'slots_unlocked' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calcula o teto real de nível do cristal: 240 + (5 × heroes Ascended do roster),
     * limitado a MAX_LEVEL. Heroes Dimensionais NÃO contam (regra do jogo).
     */
    public static function levelCapForUser(User $user): int
    {
        $ascendedNonDimensional = $user->userHeroes()
            ->whereHas('hero.tier', fn ($q) => $q->where('code', 'ascended'))
            ->whereHas('hero.faction', fn ($q) => $q->where('code', '!=', 'dimensional'))
            ->count();

        return min(
            self::MAX_LEVEL,
            self::MIN_LEVEL + ($ascendedNonDimensional * self::LEVEL_CAP_PER_ASCENDED),
        );
    }
}
