<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        $user->gameAccounts()->create([
            'name' => 'Conta Principal',
            'is_default' => true,
        ]);
    }
}
