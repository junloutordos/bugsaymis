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

    Route::middleware('permission:ipcr.v2.approve')->group(function () {
        Route::get('/division-chief/ipcr-v2', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'index'])->name('division-chief-ipcr-v2.index');
        Route::get('/division-chief/ipcr-v2/{id}', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'show'])->name('division-chief-ipcr-v2.show');
        Route::post('/division-chief/ipcr-v2/{id}/approve-targets', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'approveTargets'])->name('division-chief-ipcr-v2.approveTargets');
        Route::post('/division-chief/ipcr-v2/{id}/disapprove-targets', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'disapproveTargets'])->name('division-chief-ipcr-v2.disapproveTargets');
        Route::put('/division-chief/ipcr-v2/{id}/core-items/{coreItem}/rate', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'rateCoreItem'])->name('division-chief-ipcr-v2.rateCoreItem');
        Route::put('/division-chief/ipcr-v2/{id}/support-items/{supportItem}/rate', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'rateSupportItem'])->name('division-chief-ipcr-v2.rateSupportItem');
        Route::post('/division-chief/ipcr-v2/{id}/submit-to-pmt', [\App\Http\Controllers\IPCRV2\DivisionChiefIpcrV2Controller::class, 'submitToPMT'])->name('division-chief-ipcr-v2.submitToPMT');

        Route::post('/division-chief/ipcr-v2/{ipcrV2}/coaching-sessions', [\App\Http\Controllers\IPCRV2\IpcrV2CoachingSessionController::class, 'store'])->name('ipcr-v2-coaching-sessions.store');
        Route::delete('/division-chief/ipcr-v2/{ipcrV2}/coaching-sessions/{coachingSession}', [\App\Http\Controllers\IPCRV2\IpcrV2CoachingSessionController::class, 'destroy'])->name('ipcr-v2-coaching-sessions.destroy');

        Route::get('/pmt/ipcr-v2', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'index'])->name('pmt-ipcr-v2.index');
        Route::get('/pmt/ipcr-v2/{id}', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'show'])->name('pmt-ipcr-v2.show');
        Route::post('/pmt/ipcr-v2/{id}/approve', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'approve'])->name('pmt-ipcr-v2.approve');
        Route::post('/pmt/ipcr-v2/{id}/return', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'returnForRevision'])->name('pmt-ipcr-v2.return');
        Route::post('/pmt/ipcr-v2/{id}/director-sign', [\App\Http\Controllers\IPCRV2\PMTIpcrV2Controller::class, 'directorSign'])->name('pmt-ipcr-v2.directorSign');
    });
});
