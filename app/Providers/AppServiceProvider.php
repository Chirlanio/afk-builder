<?php

namespace App\Providers;

use App\Models\GameAccount;
use App\Models\User;
use App\Observers\GameAccountObserver;
use App\Observers\UserObserver;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('manage-heroes', fn (User $user) => (bool) $user->is_admin);

        GameAccount::observe(GameAccountObserver::class);
        User::observe(UserObserver::class);

        // Quando o worker do queue inicia, reconcilia CommandRuns órfãos
        // (status=running mas sem job correspondente em `jobs` — sinal de que
        // o worker anterior morreu antes de finalizar). Roda 1x por processo.
        Event::listen(Looping::class, function () {
            static $done = false;
            if ($done) {
                return;
            }
            $done = true;
            Artisan::call('command-runs:reconcile-stale');
        });
    }
}
