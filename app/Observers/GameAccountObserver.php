<?php

namespace App\Observers;

use App\Models\GameAccount;

class GameAccountObserver
{
    public function creating(GameAccount $account): void
    {
        $hasAny = GameAccount::where('user_id', $account->user_id)->exists();

        if (! $hasAny) {
            $account->is_default = true;
        }
    }

    public function saving(GameAccount $account): void
    {
        if ($account->is_default && $account->isDirty('is_default')) {
            GameAccount::where('user_id', $account->user_id)
                ->whereKeyNot($account->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    public function deleted(GameAccount $account): void
    {
        if (! $account->is_default) {
            return;
        }

        $next = GameAccount::where('user_id', $account->user_id)
            ->orderBy('id')
            ->first();

        if ($next !== null) {
            $next->forceFill(['is_default' => true])->save();
        }
    }
}
