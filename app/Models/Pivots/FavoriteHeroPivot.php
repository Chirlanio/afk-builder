<?php

namespace App\Models\Pivots;

use App\Models\GameAccount;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot custom pra user_hero_favorites que preenche user_id quando ausente.
 *
 * Bridge da Etapa D: quando o attach vem via $account->favoriteHeroes(),
 * o Laravel só grava game_account_id/hero_id no pivot. Como a coluna
 * user_id ainda é NOT NULL (dropada só no fim da Etapa D), este boot
 * resolve o user_id do GameAccount referenciado.
 */
class FavoriteHeroPivot extends Pivot
{
    protected $table = 'user_hero_favorites';

    protected static function booted(): void
    {
        static::creating(function (self $pivot) {
            if ($pivot->user_id === null && $pivot->game_account_id !== null) {
                $pivot->user_id = GameAccount::whereKey($pivot->game_account_id)->value('user_id');
            }

            if ($pivot->game_account_id === null && $pivot->user_id !== null) {
                $pivot->game_account_id = GameAccount::where('user_id', $pivot->user_id)
                    ->where('is_default', true)
                    ->value('id');
            }
        });
    }
}
