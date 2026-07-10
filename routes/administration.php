<?php

use App\Http\Controllers\Administration\AnnouncementController;
use App\Http\Controllers\Administration\CoaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration modules — Announcements + Certificate of Appearance
|--------------------------------------------------------------------------
| Announcements: index is open to every authenticated user (they only see
| announcements addressed to them); mutations need announcements.manage.
| COA: fully gated behind coa.manage.
| Public QR verify routes live in web.php next to the issuance verify routes.
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');

    Route::middleware('permission:announcements.manage')->group(function () {
        Route::post('/announcements',                            [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/announcements/{announcement}',              [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::post('/announcements/{announcement}/publish',     [AnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::delete('/announcements/{announcement}',           [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    Route::middleware('permission:coa.manage')->prefix('coa')->name('coa.')->group(function () {
        Route::get('/',                                  [CoaController::class, 'index'])->name('index');
        Route::post('/',                                 [CoaController::class, 'store'])->name('store');
        Route::put('/{visit}',                           [CoaController::class, 'update'])->name('update');
        Route::post('/{visit}/issue',                    [CoaController::class, 'issue'])->name('issue');
        Route::post('/certificates/{certificate}/resend', [CoaController::class, 'resend'])->name('resend');
        Route::get('/certificates/{certificate}/pdf',    [CoaController::class, 'pdf'])->name('pdf');
        Route::delete('/{visit}',                        [CoaController::class, 'destroy'])->name('destroy');
    });
});
