<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResonatingCrystal extends Model
{
    /** Nível mínimo (estado pós-upgrade do pentagrama). */
    public const MIN_LEVEL = 240;

    /**
     * Limite de validação do nível. O teto real vem do jogo (240 + 5 por herói
     * Ascended; há contas em 1040) e cresce a cada herói novo.
     */
    public const MAX_LEVEL = 2000;

    /** Último nível com custo conhecido (tabela pública da Fandom vai até 501). */
    public const COST_DATA_MAX_LEVEL = 501;

    /** Limite de validação das posições do cristal. */
    public const MAX_SLOTS = 500;

    /** Fortalecimentos necessários por nível. */
    public const STRENGTHENS_PER_LEVEL = 10;

    /** Bônus de teto por hero Ascended (catálogo). */
    public const LEVEL_CAP_PER_ASCENDED = 5;

    /** Slots iniciais (pentagrama). */
    public const INITIAL_SLOTS = 5;

    protected $fillable = [
        'game_account_id',
        'level',
        'level_cap',
        'strengthen_progress',
        'slots_unlocked',
        'slots_occupied',
    ];

    protected $attributes = [
        'level' => self::MIN_LEVEL,
        'strengthen_progress' => 0,
        'slots_unlocked' => self::INITIAL_SLOTS,
    ];

    protected $casts = [
        'level' => 'integer',
        'level_cap' => 'integer',
        'strengthen_progress' => 'integer',
        'slots_unlocked' => 'integer',
        'slots_occupied' => 'integer',
    ];

    public function gameAccount(): BelongsTo
    {
        return $this->belongsTo(GameAccount::class);
    }

    /**
     * Teto de nível do cristal. Usa o teto informado pelo jogador (o que o jogo
     * mostra, ex: 675/1040) quando existe; senão estima pelo roster cadastrado:
     * 240 + (5 × heroes Ascended), sem Dimensionais (regra do jogo).
     */
    public static function levelCapForAccount(GameAccount $account): int
    {
        $manualCap = $account->resonatingCrystal?->level_cap;
        if ($manualCap !== null) {
            return $manualCap;
        }

        $ascendedNonDimensional = $account->userHeroes()
            ->whereHas('hero.tier', fn ($q) => $q->where('code', 'ascended'))
            ->whereHas('hero.faction', fn ($q) => $q->where('code', '!=', 'dimensional'))
            ->count();

        return min(
            self::MAX_LEVEL,
            self::MIN_LEVEL + ($ascendedNonDimensional * self::LEVEL_CAP_PER_ASCENDED),
        );
    }
}
