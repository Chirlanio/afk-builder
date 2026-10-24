<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Counter: relação curada e direcional "o Herói é forte contra o alvo" num
 * Contexto. Vale para o Herói, não para o Personagem. É informativo: não entra
 * na Pontuação (ADR 0001).
 */
class HeroCounter extends Model
{
    /** Contextos aceitos: os mesmos dos ratings, menos o geral. */
    public const CONTEXTS = [
        HeroRating::MODE_PVP,
        HeroRating::MODE_PVE,
        HeroRating::MODE_TWISTED_REALM,
        HeroRating::MODE_ABYSSAL_EXPEDITION,
        HeroRating::MODE_LABYRINTH,
        HeroRating::MODE_DRAGONFORGE,
    ];

    public const MAX_STRENGTH = 2;

    protected $fillable = [
        'hero_id',
        'target_hero_id',
        'context',
        'strength',
        'reason',
        'source_url',
    ];

    protected $casts = [
        'strength' => 'integer',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Hero::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Hero::class, 'target_hero_id');
    }
}
