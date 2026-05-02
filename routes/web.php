<?php

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
    Route::delete('formations/{formation}', [FormationController::class, 'destroy'])->name('formations.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
