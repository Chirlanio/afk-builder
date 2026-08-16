<?php

namespace App\Models\Concerns;

use App\Models\GameAccount;

/**
 * Auto-preenche game_account_id com o default do user quando ausente.
 *
 * Bridge da migração Etapa A/B → Etapa D. Enquanto controllers antigos e
 * factories/testes preencherem só user_id, este boot resolve o
 * game_account_id transparentemente. Remover quando user_id for dropado
 * das tabelas de domínio.
 */
trait BelongsToGameAccount
{
    protected static function bootBelongsToGameAccount(): void
    {
        static::creating(function ($model) {
            // game_account_id ausente + user_id presente → resolve do default
            if ($model->game_account_id === null && $model->user_id !== null) {
                $model->game_account_id = GameAccount::where('user_id', $model->user_id)
                    ->where('is_default', true)
                    ->value('id');
            }

            // user_id ausente + game_account_id presente → resolve da conta
            // (bridge enquanto controllers refatorados criam via $active->relation
            // e user_id ainda é NOT NULL nas tabelas)
            if ($model->user_id === null && $model->game_account_id !== null) {
                $model->user_id = GameAccount::whereKey($model->game_account_id)->value('user_id');
            }
        });
    }
}
