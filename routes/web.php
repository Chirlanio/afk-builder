<?php

use App\Http\Controllers\Admin\HeroAdminController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\HeroController;
use App\Http\Controllers\UserHeroController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('heroes', [HeroController::class, 'index'])->name('heroes.index');

    Route::post('user/heroes', [UserHeroController::class, 'store'])->name('user-heroes.store');
    Route::patch('user/heroes/{userHero}', [UserHeroController::class, 'update'])->name('user-heroes.update');
    Route::delete('user/heroes/{userHero}', [UserHeroController::class, 'destroy'])->name('user-heroes.destroy');

    Route::get('formations', [FormationController::class, 'index'])->name('formations.index');
    Route::get('formations/create', [FormationController::class, 'create'])->name('formations.create');
    Route::post('formations', [FormationController::class, 'store'])->name('formations.store');
    Route::get('formations/{formation}/edit', [FormationController::class, 'edit'])->name('formations.edit');
    Route::patch('formations/{formation}', [FormationController::class, 'update'])->name('formations.update');
    Route::post('formations/{formation}/teams/{position}/suggest', [FormationController::class, 'suggestTeam'])
        ->whereNumber('position')
        ->name('formations.teams.suggest');
    Route::post('formations/{formation}/suggest-all', [FormationController::class, 'suggestAll'])
        ->name('formations.suggest-all');
    Route::delete('formations/{formation}', [FormationController::class, 'destroy'])->name('formations.destroy');

    Route::middleware('can:manage-heroes')->prefix('admin')->name('admin.')->group(function () {
        Route::get('heroes/pending', [HeroAdminController::class, 'pendingIndex'])->name('heroes.pending');
        Route::patch('heroes/{hero}/approve', [HeroAdminController::class, 'approve'])->name('heroes.approve');
        Route::patch('heroes/{hero}/reject', [HeroAdminController::class, 'reject'])->name('heroes.reject');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
