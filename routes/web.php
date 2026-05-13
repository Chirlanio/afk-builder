<?php

use App\Http\Controllers\Admin\BeastAdminController;
use App\Http\Controllers\Admin\CollectionAdminController;
use App\Http\Controllers\Admin\HeroAdminController;
use App\Http\Controllers\Admin\HeroStatsAdminController;
use App\Http\Controllers\Admin\SyncCommandController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormationCompareController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\FormationShareController;
use App\Http\Controllers\FormationVersionController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HeroController;
use App\Http\Controllers\RecommendedFormationController;
use App\Http\Controllers\UserHeroController;
use App\Http\Controllers\UserTierListController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::get('/f/{token}', [FormationShareController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->name('formations.public');

Route::get('/tier/{token}', [UserTierListController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->name('tier-lists.public');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
    Route::patch('user/heroes/{userHero}/stats', [UserHeroController::class, 'updateStats'])->name('user-heroes.stats.update');
    Route::delete('user/heroes/{userHero}', [UserHeroController::class, 'destroy'])->name('user-heroes.destroy');

    Route::get('formations', [FormationController::class, 'index'])->name('formations.index');
    Route::get('formations/recommended', [RecommendedFormationController::class, 'index'])
        ->name('formations.recommended.index');
    Route::post('formations/recommended/{recommended:slug}/clone', [RecommendedFormationController::class, 'clone'])
        ->where('recommended', '[a-z0-9-]+')
        ->name('formations.recommended.clone');
    Route::get('formations/compare/{a}/{b}', [FormationCompareController::class, 'show'])
        ->name('formations.compare');
    Route::get('formations/create', [FormationController::class, 'create'])->name('formations.create');
    Route::post('formations', [FormationController::class, 'store'])->name('formations.store');
    Route::get('formations/{formation}/edit', [FormationController::class, 'edit'])->name('formations.edit');
    Route::patch('formations/{formation}', [FormationController::class, 'update'])->name('formations.update');
    Route::patch('formations/{formation}/scoring-weights', [FormationController::class, 'updateScoringWeights'])
        ->name('formations.scoring-weights.update');
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

    Route::get('tier-lists', [UserTierListController::class, 'index'])->name('tier-lists.index');
    Route::get('tier-lists/create', [UserTierListController::class, 'create'])->name('tier-lists.create');
    Route::post('tier-lists', [UserTierListController::class, 'store'])->name('tier-lists.store');
    Route::get('tier-lists/{tierList}/edit', [UserTierListController::class, 'edit'])->name('tier-lists.edit');
    Route::patch('tier-lists/{tierList}', [UserTierListController::class, 'update'])->name('tier-lists.update');
    Route::delete('tier-lists/{tierList}', [UserTierListController::class, 'destroy'])->name('tier-lists.destroy');
    Route::post('tier-lists/{tierList}/rows', [UserTierListController::class, 'addRow'])->name('tier-lists.rows.add');
    Route::patch('tier-lists/{tierList}/rows/{row}', [UserTierListController::class, 'updateRow'])->name('tier-lists.rows.update');
    Route::delete('tier-lists/{tierList}/rows/{row}', [UserTierListController::class, 'deleteRow'])->name('tier-lists.rows.delete');
    Route::post('tier-lists/{tierList}/rows/{row}/reorder-items', [UserTierListController::class, 'reorderItems'])->name('tier-lists.rows.reorder-items');
    Route::post('tier-lists/{tierList}/place-hero', [UserTierListController::class, 'placeHero'])->name('tier-lists.place-hero');
    Route::post('tier-lists/{tierList}/share', [UserTierListController::class, 'share'])->name('tier-lists.share');
    Route::delete('tier-lists/{tierList}/share', [UserTierListController::class, 'unshare'])->name('tier-lists.share.revoke');

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

        Route::get('beasts', [BeastAdminController::class, 'index'])->name('beasts.index');
        Route::post('beasts', [BeastAdminController::class, 'store'])->name('beasts.store');
        Route::post('beasts/upload-image', [BeastAdminController::class, 'uploadImage'])->name('beasts.upload-image');
        Route::patch('beasts/{beast}', [BeastAdminController::class, 'update'])->name('beasts.update');
        Route::delete('beasts/{beast}', [BeastAdminController::class, 'destroy'])->name('beasts.destroy');

        Route::get('sync', [SyncCommandController::class, 'index'])->name('sync.index');
        Route::get('sync/runs', [SyncCommandController::class, 'runs'])->name('sync.runs');
        Route::get('sync/runs/{run}', [SyncCommandController::class, 'show'])->name('sync.runs.show');
        Route::post('sync', [SyncCommandController::class, 'store'])->name('sync.store');
        Route::delete('sync/runs/{run}', [SyncCommandController::class, 'cancel'])->name('sync.cancel');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
