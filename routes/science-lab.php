<?php

use App\Http\Controllers\ScienceLab\LabCalibrationController;
use App\Http\Controllers\ScienceLab\LabConsumableController;
use App\Http\Controllers\ScienceLab\LabDashboardController;
use App\Http\Controllers\ScienceLab\LabEquipmentController;
use App\Http\Controllers\ScienceLab\LabEquipmentRequestController;
use App\Http\Controllers\ScienceLab\LabMaintenanceController;
use App\Http\Controllers\ScienceLab\LaboratoryController;
use App\Http\Controllers\ScienceLab\LabReagentRequestController;
use App\Http\Controllers\ScienceLab\LabReportController;
use App\Http\Controllers\ScienceLab\LabReservationController;
use App\Http\Controllers\ScienceLab\LabRequestBundleController;
use App\Http\Controllers\ScienceLab\LabSafetyController;
use App\Http\Controllers\ScienceLab\LabWasteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Science Laboratory Management (CIM 4.4) — under Curriculum & Instruction
|--------------------------------------------------------------------------
| Route ordering note: literal segments are declared BEFORE any wildcard
| {param} routes under the same prefix to avoid the wildcard swallowing them.
*/

Route::middleware(['auth', 'permission:lab.view|lab.request|lab.manage'])
    ->prefix('science-lab')
    ->name('science-lab.')
    ->group(function () {

        Route::get('/', [LabDashboardController::class, 'index'])->name('dashboard');

        // Unified requester journey + SRA/SRS operational queue.
        Route::get('/requests', [LabRequestBundleController::class, 'index'])->name('requests.index');
        Route::post('/requests', [LabRequestBundleController::class, 'store'])->name('requests.store');
        Route::put('/requests/{bundle}', [LabRequestBundleController::class, 'update'])->name('requests.update');
        Route::post('/requests/{bundle}/endorse', [LabRequestBundleController::class, 'endorse'])->name('requests.endorse');
        Route::post('/requests/{bundle}/approve', [LabRequestBundleController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{bundle}/cancel', [LabRequestBundleController::class, 'cancel'])->name('requests.cancel');
        Route::post('/requests/{bundle}/decline', [LabRequestBundleController::class, 'decline'])->name('requests.decline');
        Route::post('/requests/{bundle}/complete', [LabRequestBundleController::class, 'complete'])->name('requests.complete');

        // ── Setup: Laboratories (rooms tagged as science labs) ──────────────────
        Route::middleware('permission:lab.manage')->group(function () {
            Route::get('/laboratories',                    [LaboratoryController::class, 'index'])->name('laboratories.index');
            Route::post('/laboratories',                   [LaboratoryController::class, 'store'])->name('laboratories.store');
            Route::put('/laboratories/{profile}',          [LaboratoryController::class, 'update'])->name('laboratories.update');
            Route::delete('/laboratories/{profile}',       [LaboratoryController::class, 'destroy'])->name('laboratories.destroy');

            // ── Equipment registry ──────────────────────────────────────────────
            Route::get('/equipment',                       [LabEquipmentController::class, 'index'])->name('equipment.index');
            Route::post('/equipment',                      [LabEquipmentController::class, 'store'])->name('equipment.store');
            Route::get('/equipment/{equipment}',           [LabEquipmentController::class, 'show'])->name('equipment.show');
            Route::put('/equipment/{equipment}',           [LabEquipmentController::class, 'update'])->name('equipment.update');
            Route::delete('/equipment/{equipment}',        [LabEquipmentController::class, 'destroy'])->name('equipment.destroy');

            // ── Consumables & reagents inventory ────────────────────────────────
            Route::get('/inventory',                       [LabConsumableController::class, 'index'])->name('inventory.index');
            Route::post('/inventory',                      [LabConsumableController::class, 'store'])->name('inventory.store');
            Route::get('/inventory/{consumable}',          [LabConsumableController::class, 'show'])->name('inventory.show');
            Route::put('/inventory/{consumable}',          [LabConsumableController::class, 'update'])->name('inventory.update');
            Route::post('/inventory/{consumable}/receive', [LabConsumableController::class, 'receive'])->name('inventory.receive');
            Route::post('/inventory/{consumable}/adjust',  [LabConsumableController::class, 'adjust'])->name('inventory.adjust');
            Route::post('/inventory/lots/{lot}/dispose',   [LabConsumableController::class, 'disposeLot'])->name('inventory.lots.dispose');
        });

        // ── Reservations (CID-05) ───────────────────────────────────────────────
        Route::get('/reservations',                 [LabReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations',                [LabReservationController::class, 'store'])->name('reservations.store');
        Route::post('/reservations/{reservation}/endorse', [LabReservationController::class, 'endorse'])->name('reservations.endorse');
        Route::post('/reservations/{reservation}/approve', [LabReservationController::class, 'approve'])->name('reservations.approve');
        Route::post('/reservations/{reservation}/decline', [LabReservationController::class, 'decline'])->name('reservations.decline');
        Route::post('/reservations/{reservation}/cancel',  [LabReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::post('/reservations/{reservation}/complete',[LabReservationController::class, 'complete'])->name('reservations.complete');
        Route::get('/reservations/{reservation}/pdf',      [LabReservationController::class, 'pdf'])->name('reservations.pdf');

        // ── Equipment accountability requests (CID-20) ──────────────────────────
        Route::get('/equipment-requests',                  [LabEquipmentRequestController::class, 'index'])->name('equipment-requests.index');
        Route::post('/equipment-requests',                 [LabEquipmentRequestController::class, 'store'])->name('equipment-requests.store');
        Route::post('/equipment-requests/{equipmentRequest}/endorse',[LabEquipmentRequestController::class, 'endorse'])->name('equipment-requests.endorse');
        Route::post('/equipment-requests/{equipmentRequest}/approve',[LabEquipmentRequestController::class, 'approve'])->name('equipment-requests.approve');
        Route::post('/equipment-requests/{equipmentRequest}/issue',  [LabEquipmentRequestController::class, 'issue'])->name('equipment-requests.issue');
        Route::post('/equipment-requests/{equipmentRequest}/return', [LabEquipmentRequestController::class, 'returnItems'])->name('equipment-requests.return');
        Route::post('/equipment-requests/{equipmentRequest}/decline',[LabEquipmentRequestController::class, 'decline'])->name('equipment-requests.decline');
        Route::get('/equipment-requests/{equipmentRequest}/pdf',     [LabEquipmentRequestController::class, 'pdf'])->name('equipment-requests.pdf');

        // ── Reagent requests (CID-19) ───────────────────────────────────────────
        Route::get('/reagent-requests',                  [LabReagentRequestController::class, 'index'])->name('reagent-requests.index');
        Route::post('/reagent-requests',                 [LabReagentRequestController::class, 'store'])->name('reagent-requests.store');
        Route::post('/reagent-requests/{reagentRequest}/endorse',[LabReagentRequestController::class, 'endorse'])->name('reagent-requests.endorse');
        Route::post('/reagent-requests/{reagentRequest}/approve',[LabReagentRequestController::class, 'approve'])->name('reagent-requests.approve');
        Route::post('/reagent-requests/{reagentRequest}/release',[LabReagentRequestController::class, 'release'])->name('reagent-requests.release');
        Route::post('/reagent-requests/{reagentRequest}/decline',[LabReagentRequestController::class, 'decline'])->name('reagent-requests.decline');
        Route::get('/reagent-requests/{reagentRequest}/pdf',     [LabReagentRequestController::class, 'pdf'])->name('reagent-requests.pdf');

        // ── Maintenance: PM schedules + repair/service (CID-08, CID-10) ─────────
        Route::middleware('permission:lab.manage')->group(function () {
            Route::get('/maintenance',                       [LabMaintenanceController::class, 'index'])->name('maintenance.index');
            Route::post('/maintenance/schedules',            [LabMaintenanceController::class, 'storeSchedule'])->name('maintenance.schedules.store');
            Route::post('/maintenance/records',              [LabMaintenanceController::class, 'updateRecord'])->name('maintenance.records.update');
            Route::post('/maintenance/tickets',              [LabMaintenanceController::class, 'storeTicket'])->name('maintenance.tickets.store');
            Route::put('/maintenance/tickets/{ticket}',      [LabMaintenanceController::class, 'updateTicket'])->name('maintenance.tickets.update');
            Route::post('/maintenance/service-logs',         [LabMaintenanceController::class, 'storeServiceLog'])->name('maintenance.service-logs.store');
            Route::get('/maintenance/schedule/pdf',          [LabMaintenanceController::class, 'schedulePdf'])->name('maintenance.schedule.pdf');
            Route::get('/maintenance/equipment/{equipment}/service-form/pdf', [LabMaintenanceController::class, 'servicePdf'])->name('maintenance.service-form.pdf');

            // ── Calibration (CID-09) ────────────────────────────────────────────
            Route::get('/calibration',                       [LabCalibrationController::class, 'index'])->name('calibration.index');
            Route::post('/calibration/schedules',            [LabCalibrationController::class, 'storeSchedule'])->name('calibration.schedules.store');
            Route::post('/calibration/schedules/{schedule}/submit', [LabCalibrationController::class, 'submit'])->name('calibration.schedules.submit');
            Route::post('/calibration/events',               [LabCalibrationController::class, 'storeEvent'])->name('calibration.events.store');
            Route::put('/calibration/events/{event}',        [LabCalibrationController::class, 'updateEvent'])->name('calibration.events.update');
            Route::get('/calibration/schedule/pdf',          [LabCalibrationController::class, 'schedulePdf'])->name('calibration.schedule.pdf');

            // ── Safety & waste ──────────────────────────────────────────────────
            Route::get('/safety',                            [LabSafetyController::class, 'index'])->name('safety.index');
            Route::post('/safety/procedures',                [LabSafetyController::class, 'storeProcedure'])->name('safety.procedures.store');
            Route::put('/safety/procedures/{procedure}',     [LabSafetyController::class, 'updateProcedure'])->name('safety.procedures.update');
            Route::post('/safety/orientations',              [LabSafetyController::class, 'storeOrientation'])->name('safety.orientations.store');
            Route::post('/safety/first-aid',                 [LabSafetyController::class, 'storeFirstAid'])->name('safety.first-aid.store');

            Route::get('/waste',                             [LabWasteController::class, 'index'])->name('waste.index');
            Route::post('/waste',                            [LabWasteController::class, 'store'])->name('waste.store');
            Route::put('/waste/{log}',                       [LabWasteController::class, 'update'])->name('waste.update');
            Route::delete('/waste/{log}',                    [LabWasteController::class, 'destroy'])->name('waste.destroy');
        });

        // ── Calibration approval (AUH/CID Chief) ────────────────────────────────
        Route::post('/calibration/schedules/{schedule}/approve', [LabCalibrationController::class, 'approve'])
            ->middleware('permission:lab.calibration.approve')
            ->name('calibration.schedules.approve');

        // ── Reports ─────────────────────────────────────────────────────────────
        Route::get('/reports/inventory', [LabReportController::class, 'inventory'])
            ->middleware('permission:lab.reports')
            ->name('reports.inventory');
    });
