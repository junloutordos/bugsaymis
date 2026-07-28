<?php

use App\Http\Controllers\CID\CompetitionController;
use App\Http\Controllers\CidDashboardController;
use App\Http\Controllers\SchoolCalendarController;
use Illuminate\Support\Facades\Route;

// Academic Calendar (holidays / class suspensions) — feeds
// SchoolCalendarService, consumed by the Homeroom Attendance schedule-driven
// subject-attendance sync and the monthly Record on Attendance and
// Punctuality's school-days count.
Route::middleware(['auth', 'permission:academic-calendar.manage'])
    ->prefix('academic-calendar')
    ->name('academic-calendar.')
    ->group(function () {
        Route::get('/',            [SchoolCalendarController::class, 'index'])->name('index');
        Route::post('/',           [SchoolCalendarController::class, 'store'])->name('store');
        Route::delete('/{event}',  [SchoolCalendarController::class, 'destroy'])->name('destroy');
    });

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

// Competitions & Winnings — employees log competition participation/awards for
// themselves and for the students they coach. Per-record edit/delete checks
// (creator or cid.competitions.manage) live in CompetitionController.
Route::middleware(['auth', 'permission:cid.competitions.view|cid.competitions.manage'])
    ->prefix('cid/competitions')
    ->name('cid.competitions.')
    ->group(function () {
        Route::get('/',                 [CompetitionController::class, 'index'])->name('index');
        Route::get('/students/search',  [CompetitionController::class, 'searchStudents'])->name('students.search');
        Route::post('/',                [CompetitionController::class, 'store'])->name('store');
        Route::put('/{competition}',    [CompetitionController::class, 'update'])->name('update');
        Route::delete('/{competition}', [CompetitionController::class, 'destroy'])->name('destroy');
    });
