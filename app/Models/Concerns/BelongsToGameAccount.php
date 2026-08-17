<?php

namespace App\Models\Concerns;

use App\Models\GameAccount;

/**
 * Compat de testes: quando um create() vem sem game_account_id mas o array
 * inclui user_id (padrão legacy), resolve o game_account_id do default do
 * user. Sem essa trait, tests herdados que criam via Model::create(['user_id'
 * => $u->id, ...]) explodiriam com NOT NULL violation.
 *
 * Precisa que 'user_id' esteja em $fillable pra que Model::create() aceite
 * o input — mesmo user_id não sendo coluna real. O boot descarta o atributo
 * antes do save.
 *
 * TODO(D.4): factories dedicadas vão passar game_account_id direto → esta
 * trait pode virar dead code + drop no fim.
 */
trait BelongsToGameAccount
{
    protected static function bootBelongsToGameAccount(): void
    {
        static::creating(function ($model) {
            $userId = $model->getAttributes()['user_id'] ?? null;

            if ($userId !== null) {
                if ($model->game_account_id === null) {
                    $model->game_account_id = GameAccount::where('user_id', $userId)
                        ->where('is_default', true)
                        ->value('id');
                }
                // Remove user_id do array de atributos (não existe como coluna real).
                unset($model->attributes['user_id']);
            }
        });
    }
}
