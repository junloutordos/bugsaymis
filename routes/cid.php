<?php

use App\Http\Controllers\CidDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:cid.dashboard'])
    ->prefix('cid')
    ->name('cid.')
    ->group(function () {
        Route::get('/dashboard',              [CidDashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/events',       [CidDashboardController::class, 'events'])->name('dashboard.events');
        Route::post('/dashboard',             [CidDashboardController::class, 'store'])->name('dashboard.store');
        Route::put('/dashboard/{schedule}',   [CidDashboardController::class, 'update'])->name('dashboard.update');
        Route::delete('/dashboard/{schedule}',[CidDashboardController::class, 'destroy'])->name('dashboard.destroy');
    });
