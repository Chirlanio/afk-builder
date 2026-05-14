<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('heroes:sync')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('heroes:resolve-images')
    ->dailyAt('03:50')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Fase 46: snapshot diário do power do roster por user — alimenta o chart de evolução
Schedule::command('roster:snapshot')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();
