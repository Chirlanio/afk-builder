<?php

namespace App\Services;

use App\Models\GameAccount;
use App\Models\User;
use Illuminate\Http\Request;
use RuntimeException;

class ActiveGameAccount
{
    public const SESSION_KEY = 'active_game_account_id';

    public function __construct(private readonly Request $request) {}

    /**
     * Resolve a conta ativa do user autenticado.
     * Fallback: default do user (is_default=1); caso não haja default, primeira conta.
     */
    public function get(): GameAccount
    {
        $user = $this->request->user();

        if ($user === null) {
            throw new RuntimeException('ActiveGameAccount::get() called without authenticated user');
        }

        $id = $this->request->session()->get(self::SESSION_KEY);

        $account = $id !== null
            ? $user->gameAccounts()->find($id)
            : null;

        if ($account === null) {
            $account = $this->resolveFallback($user);
            $this->request->session()->put(self::SESSION_KEY, $account->id);
        }

        return $account;
    }

    public function switch(GameAccount $account): void
    {
        $user = $this->request->user();

        if ($user === null || $account->user_id !== $user->id) {
            abort(403);
        }

        $this->request->session()->put(self::SESSION_KEY, $account->id);
    }

    private function resolveFallback(User $user): GameAccount
    {
        return $user->gameAccounts()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->firstOrFail();
    }
}
