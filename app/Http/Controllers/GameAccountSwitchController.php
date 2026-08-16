<?php

namespace App\Http\Controllers;

use App\Models\GameAccount;
use App\Services\ActiveGameAccount;
use Illuminate\Http\RedirectResponse;

class GameAccountSwitchController extends Controller
{
    public function __invoke(GameAccount $gameAccount, ActiveGameAccount $active): RedirectResponse
    {
        $active->switch($gameAccount);

        return back();
    }
}
