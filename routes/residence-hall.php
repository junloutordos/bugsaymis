<?php

use App\Http\Controllers\ResidenceHall\RhApplicationController;
use App\Http\Controllers\ResidenceHall\RhApplianceController;
use App\Http\Controllers\ResidenceHall\RhDashboardController;
use App\Http\Controllers\ResidenceHall\RhFeeController;
use App\Http\Controllers\ResidenceHall\RhHousekeepingController;
use App\Http\Controllers\ResidenceHall\RhIncidentController;
use App\Http\Controllers\ResidenceHall\RhInternController;
use App\Http\Controllers\ResidenceHall\RhLeavePassController;
use App\Http\Controllers\ResidenceHall\RhRoomController;
use App\Http\Controllers\ResidenceHall\RhWaiverController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Residence Hall (RHU) Routes
|--------------------------------------------------------------------------
|
| Permission scopes:
|   rh.dashboard.view          — view dashboard
|   rh.rooms.manage            — manage room catalog
|   rh.applications.view       — view applications
|   rh.applications.evaluate   — evaluate / recommend (RH Committee)
|   rh.applications.approve    — approve / reject (Campus Director)
|   rh.interns.view            — view intern roster
|   rh.interns.manage          — room assignment, check-in/out, contract
|   rh.leave-passes.view       — view leave pass queue
|   rh.leave-passes.approve    — approve / reject leave passes
|   rh.leave-passes.guard      — guard departure / return logging
|   rh.housekeeping.manage     — housekeeping checks and conformes
|   rh.incidents.manage        — log and resolve incidents
|   rh.fees.manage             — fee ledger and payment recording
|   rh.reports.view            — monthly reports
|
*/

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('rh')
    ->name('rh.')
    ->group(function () {

        // ── Dashboard ─────────────────────────────────────────────────────────
        Route::middleware('permission:rh.dashboard.view')
            ->get('/dashboard', [RhDashboardController::class, 'index'])
            ->name('dashboard');

        // ── Rooms ─────────────────────────────────────────────────────────────
        Route::middleware('permission:rh.rooms.manage')->group(function () {
            Route::get('/rooms', [RhRoomController::class, 'index'])->name('rooms.index');
            Route::get('/rooms/{rhRoom}', [RhRoomController::class, 'show'])->name('rooms.show');
            Route::post('/rooms', [RhRoomController::class, 'store'])->name('rooms.store');
            Route::put('/rooms/{rhRoom}', [RhRoomController::class, 'update'])->name('rooms.update');
            Route::delete('/rooms/{rhRoom}', [RhRoomController::class, 'destroy'])->name('rooms.destroy');
            Route::post('/rooms/{rhRoom}/assign-bed', [RhRoomController::class, 'assignBed'])->name('rooms.assign-bed');
            Route::post('/rooms/{rhRoom}/unassign-bed', [RhRoomController::class, 'unassignBed'])->name('rooms.unassign-bed');
        });

        // ── Applications ──────────────────────────────────────────────────────
        Route::middleware('permission:rh.applications.view|rh.applications.evaluate|rh.applications.approve')
            ->get('/applications', [RhApplicationController::class, 'index'])
            ->name('applications.index');

        Route::middleware('permission:rh.applications.view|rh.applications.evaluate|rh.applications.approve')
            ->get('/applications/{rhApplication}', [RhApplicationController::class, 'show'])
            ->name('applications.show');

        Route::middleware('permission:rh.applications.evaluate')->group(function () {
            Route::post('/applications', [RhApplicationController::class, 'store'])->name('applications.store');
            Route::post('/applications/{rhApplication}/evaluate', [RhApplicationController::class, 'evaluate'])->name('applications.evaluate');
        });

        Route::middleware('permission:rh.applications.approve')
            ->post('/applications/{rhApplication}/approve', [RhApplicationController::class, 'approve'])
            ->name('applications.approve');

        // ── Interns ───────────────────────────────────────────────────────────
        Route::middleware('permission:rh.interns.view|rh.interns.manage')
            ->get('/interns', [RhInternController::class, 'index'])
            ->name('interns.index');

        Route::middleware('permission:rh.interns.view|rh.interns.manage')
            ->get('/interns/{rhIntern}', [RhInternController::class, 'show'])
            ->name('interns.show');

        Route::middleware('permission:rh.interns.manage')->group(function () {
            Route::post('/interns', [RhInternController::class, 'store'])->name('interns.store');
            Route::put('/interns/{rhIntern}', [RhInternController::class, 'update'])->name('interns.update');
            Route::post('/interns/{rhIntern}/check-out', [RhInternController::class, 'checkOut'])->name('interns.check-out');
        });

        // ── Waiver (per intern) ───────────────────────────────────────────────
        Route::middleware('permission:rh.interns.manage')
            ->post('/interns/{rhIntern}/waiver', [RhWaiverController::class, 'save'])
            ->name('interns.waiver.save');

        // ── Appliances (per intern) ───────────────────────────────────────────
        Route::middleware('permission:rh.interns.manage')->group(function () {
            Route::post('/interns/{rhIntern}/appliances', [RhApplianceController::class, 'store'])->name('appliances.store');
            Route::put('/appliances/{rhAppliance}', [RhApplianceController::class, 'update'])->name('appliances.update');
            Route::delete('/appliances/{rhAppliance}', [RhApplianceController::class, 'destroy'])->name('appliances.destroy');
            Route::post('/appliances/{rhAppliance}/approve', [RhApplianceController::class, 'approve'])->name('appliances.approve');
        });

        // ── Leave Passes ──────────────────────────────────────────────────────
        Route::middleware('permission:rh.leave-passes.view|rh.leave-passes.approve|rh.leave-passes.guard')
            ->get('/leave-passes', [RhLeavePassController::class, 'index'])
            ->name('leave-passes.index');

        Route::middleware('permission:rh.leave-passes.view|rh.leave-passes.approve')
            ->post('/leave-passes', [RhLeavePassController::class, 'store'])
            ->name('leave-passes.store');

        Route::middleware('permission:rh.leave-passes.approve')
            ->post('/leave-passes/{rhLeavePass}/approve', [RhLeavePassController::class, 'approve'])
            ->name('leave-passes.approve');

        Route::middleware('permission:rh.leave-passes.guard')->group(function () {
            Route::post('/leave-passes/{rhLeavePass}/depart', [RhLeavePassController::class, 'depart'])->name('leave-passes.depart');
            Route::post('/leave-passes/{rhLeavePass}/return', [RhLeavePassController::class, 'returnIn'])->name('leave-passes.return');
        });

        // ── Housekeeping ──────────────────────────────────────────────────────
        Route::middleware('permission:rh.housekeeping.manage')->group(function () {
            Route::get('/housekeeping', [RhHousekeepingController::class, 'index'])->name('housekeeping.index');
            Route::post('/housekeeping', [RhHousekeepingController::class, 'store'])->name('housekeeping.store');
            Route::post('/housekeeping/{rhHousekeepingCheck}/conforme', [RhHousekeepingController::class, 'conforme'])->name('housekeeping.conforme');
        });

        // ── Incidents ─────────────────────────────────────────────────────────
        Route::middleware('permission:rh.incidents.manage')->group(function () {
            Route::get('/incidents', [RhIncidentController::class, 'index'])->name('incidents.index');
            Route::post('/incidents', [RhIncidentController::class, 'store'])->name('incidents.store');
            Route::put('/incidents/{rhIncident}', [RhIncidentController::class, 'update'])->name('incidents.update');
            Route::post('/incidents/{rhIncident}/resolve', [RhIncidentController::class, 'resolve'])->name('incidents.resolve');
        });

        // ── Fee Ledger ────────────────────────────────────────────────────────
        Route::middleware('permission:rh.fees.manage')->group(function () {
            Route::get('/fees', [RhFeeController::class, 'index'])->name('fees.index');
            Route::post('/fees/generate', [RhFeeController::class, 'generate'])->name('fees.generate');
            Route::post('/fees/{rhFeeLedger}/mark-paid', [RhFeeController::class, 'markPaid'])->name('fees.mark-paid');
        });

        // ── Student search (shared across RH controllers) ─────────────────────
        Route::middleware('permission:rh.applications.evaluate|rh.interns.manage')
            ->get('/students/search', function (\Illuminate\Http\Request $request) {
                $resolver = app(\App\Services\Discipline\StudentResolver::class);
                return response()->json($resolver->search($request->query('q', '')));
            })->name('students.search');
    });
