<?php

use App\Http\Controllers\Admin\CollectionAdminController;
use App\Http\Controllers\Admin\HeroAdminController;
use App\Http\Controllers\Admin\HeroStatsAdminController;
use App\Http\Controllers\FormationCompareController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\FormationShareController;
use App\Http\Controllers\FormationVersionController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HeroController;
use App\Http\Controllers\UserHeroController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/f/{token}', [FormationShareController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->name('formations.public');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('heroes', [HeroController::class, 'index'])->name('heroes.index');
    Route::get('heroes/{slug}/details', [HeroController::class, 'details'])
        ->where('slug', '[a-z0-9-]+')
        ->name('heroes.details');
    Route::get('heroes/{slug}', [HeroController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('heroes.show');

    Route::get('guias', [GuideController::class, 'index'])->name('guides.index');
    Route::get('guias/{slug}', [GuideController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')
        ->name('guides.show');

    Route::post('user/heroes', [UserHeroController::class, 'store'])->name('user-heroes.store');
    Route::patch('user/heroes/{userHero}', [UserHeroController::class, 'update'])->name('user-heroes.update');
    Route::delete('user/heroes/{userHero}', [UserHeroController::class, 'destroy'])->name('user-heroes.destroy');

    Route::get('formations', [FormationController::class, 'index'])->name('formations.index');
    Route::get('formations/compare/{a}/{b}', [FormationCompareController::class, 'show'])
        ->name('formations.compare');
    Route::get('formations/create', [FormationController::class, 'create'])->name('formations.create');
    Route::post('formations', [FormationController::class, 'store'])->name('formations.store');
    Route::get('formations/{formation}/edit', [FormationController::class, 'edit'])->name('formations.edit');
    Route::patch('formations/{formation}', [FormationController::class, 'update'])->name('formations.update');
    Route::post('formations/{formation}/teams/{position}/suggest', [FormationController::class, 'suggestTeam'])
        ->whereNumber('position')
        ->name('formations.teams.suggest');
    Route::post('formations/{formation}/suggest-all', [FormationController::class, 'suggestAll'])
        ->name('formations.suggest-all');
    Route::post('formations/{formation}/share', [FormationShareController::class, 'share'])
        ->name('formations.share');
    Route::delete('formations/{formation}/share', [FormationShareController::class, 'revoke'])
        ->name('formations.share.revoke');
    Route::get('formations/{formation}/versions', [FormationVersionController::class, 'index'])
        ->name('formations.versions.index');
    Route::post('formations/{formation}/versions/{version}/restore', [FormationVersionController::class, 'restore'])
        ->name('formations.versions.restore');
    Route::delete('formations/{formation}', [FormationController::class, 'destroy'])->name('formations.destroy');

    Route::middleware('can:manage-heroes')->prefix('admin')->name('admin.')->group(function () {
        Route::get('heroes/pending', [HeroAdminController::class, 'pendingIndex'])->name('heroes.pending');
        Route::post('heroes', [HeroAdminController::class, 'storeManual'])->name('heroes.store');
        Route::patch('heroes/{hero}/approve', [HeroAdminController::class, 'approve'])->name('heroes.approve');
        Route::patch('heroes/{hero}/reject', [HeroAdminController::class, 'reject'])->name('heroes.reject');
        Route::patch('heroes/{slug}/stats', [HeroStatsAdminController::class, 'update'])
            ->where('slug', '[a-z0-9-]+')
            ->name('heroes.stats.update');

        Route::get('collections', [CollectionAdminController::class, 'index'])->name('collections.index');
        Route::post('collections', [CollectionAdminController::class, 'store'])->name('collections.store');
        Route::patch('collections/{collection}', [CollectionAdminController::class, 'update'])->name('collections.update');
        Route::delete('collections/{collection}', [CollectionAdminController::class, 'destroy'])->name('collections.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
