<?php

use App\Http\Controllers\IPCRV2\EmployeeIpcrV2Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'pshs.email'])->group(function () {
    Route::middleware('permission:ipcr.v2.view')->group(function () {
        Route::get('/employee-ipcr-v2', [EmployeeIpcrV2Controller::class, 'index'])->name('employee-ipcr-v2.index');
        Route::get('/employee-ipcr-v2/{id}', [EmployeeIpcrV2Controller::class, 'show'])->name('employee-ipcr-v2.show');
    });

    Route::middleware('permission:ipcr.v2.create')->group(function () {
        Route::post('/employee-ipcr-v2/generate-targets', [EmployeeIpcrV2Controller::class, 'generateTargets'])->name('employee-ipcr-v2.generateTargets');
    });

    Route::middleware('permission:ipcr.v2.submit')->group(function () {
        Route::post('/employee-ipcr-v2/{id}/submit-review', [EmployeeIpcrV2Controller::class, 'submitForReview'])->name('employee-ipcr-v2.submitReview');
        Route::post('/employee-ipcr-v2/{id}/submit-rating', [EmployeeIpcrV2Controller::class, 'submitForRating'])->name('employee-ipcr-v2.submitRating');
    });

    Route::middleware('permission:ipcr.v2.update')->group(function () {
        Route::put('/employee-ipcr-v2/{id}/core-items/{coreItem}', [EmployeeIpcrV2Controller::class, 'updateCoreItem'])->name('employee-ipcr-v2.updateCoreItem');
        Route::put('/employee-ipcr-v2/{id}/support-items/{supportItem}', [EmployeeIpcrV2Controller::class, 'updateSupportItem'])->name('employee-ipcr-v2.updateSupportItem');
    });
});
