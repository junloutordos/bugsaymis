<?php

use App\Http\Controllers\Supply\IarController;
use App\Http\Controllers\Supply\ItemController;
use App\Http\Controllers\Supply\RisController;
use App\Http\Controllers\Supply\StockCardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Supply Item Catalog ───────────────────────────────────────────────────
    Route::middleware('permission:supply.view|supply.manage')->group(function () {
        Route::get('/supply/items', [ItemController::class, 'index'])->name('supply.items.index');
    });

    Route::middleware('permission:supply.manage')->group(function () {
        Route::post('/supply/items',                          [ItemController::class, 'store'])->name('supply.items.store');
        Route::put('/supply/items/{item}',                    [ItemController::class, 'update'])->name('supply.items.update');
        Route::post('/supply/items/{item}/toggle-active',     [ItemController::class, 'toggleActive'])->name('supply.items.toggle-active');
        Route::post('/supply/categories',                     [ItemController::class, 'storeCategory'])->name('supply.categories.store');
        Route::put('/supply/categories/{category}',           [ItemController::class, 'updateCategory'])->name('supply.categories.update');
        Route::get('/supply/items/import/template',           [ItemController::class, 'downloadTemplate'])->name('supply.items.import.template');
        Route::post('/supply/items/import/preview',           [ItemController::class, 'previewImport'])->name('supply.items.import.preview');
        Route::post('/supply/items/import',                   [ItemController::class, 'importCsv'])->name('supply.items.import');
    });

    // ── Inspection & Acceptance Reports (IAR) ────────────────────────────────
    Route::middleware('permission:supply.receive|supply.manage')->group(function () {
        Route::get('/supply/iar',            [IarController::class, 'index'])->name('supply.iar.index');
        Route::get('/supply/iar/create',     [IarController::class, 'create'])->name('supply.iar.create');
        Route::post('/supply/iar',           [IarController::class, 'store'])->name('supply.iar.store');
        Route::get('/supply/iar/{iar}',      [IarController::class, 'show'])->name('supply.iar.show');
        Route::post('/supply/iar/{iar}/inspect', [IarController::class, 'inspect'])->name('supply.iar.inspect');
        Route::post('/supply/iar/{iar}/accept',  [IarController::class, 'accept'])->name('supply.iar.accept');
        Route::get('/supply/iar/{iar}/pdf',  [IarController::class, 'printPdf'])->name('supply.iar.pdf');
    });

    // ── Requisition & Issue Slips (RIS) ──────────────────────────────────────
    Route::middleware('permission:supply.view|supply.issue|supply.manage')->group(function () {
        Route::get('/supply/ris',            [RisController::class, 'index'])->name('supply.ris.index');
        Route::get('/supply/ris/create',     [RisController::class, 'create'])->name('supply.ris.create');
        Route::post('/supply/ris',           [RisController::class, 'store'])->name('supply.ris.store');
        Route::get('/supply/ris/{ris}',      [RisController::class, 'show'])->name('supply.ris.show');
        Route::post('/supply/ris/{ris}/submit',  [RisController::class, 'submit'])->name('supply.ris.submit');
        Route::get('/supply/ris/{ris}/pdf',  [RisController::class, 'printPdf'])->name('supply.ris.pdf');
    });

    Route::middleware('permission:supply.manage')->group(function () {
        Route::post('/supply/ris/{ris}/approve', [RisController::class, 'approve'])->name('supply.ris.approve');
        Route::post('/supply/ris/{ris}/reject',  [RisController::class, 'reject'])->name('supply.ris.reject');
    });

    Route::middleware('permission:supply.issue|supply.manage')->group(function () {
        Route::post('/supply/ris/{ris}/issue', [RisController::class, 'issue'])->name('supply.ris.issue');
    });

    // ── Stock Card / Inventory ────────────────────────────────────────────────
    Route::middleware('permission:supply.view|supply.manage')->group(function () {
        Route::get('/supply/stock-card',           [StockCardController::class, 'index'])->name('supply.stock-card.index');
        Route::get('/supply/stock-card/{item}',    [StockCardController::class, 'show'])->name('supply.stock-card.show');
    });

    Route::middleware('permission:supply.manage')->group(function () {
        Route::post('/supply/stock-card/{item}/adjust', [StockCardController::class, 'adjust'])->name('supply.stock-card.adjust');
    });
});
