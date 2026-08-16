<?php

namespace App\Http\Middleware;

use App\Services\ActiveGameAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGameAccount
{
    public function __construct(private readonly ActiveGameAccount $activeGameAccount) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && $request->user()->gameAccounts()->exists()) {
            $this->activeGameAccount->get();
        }

        return $next($request);
    }
}
