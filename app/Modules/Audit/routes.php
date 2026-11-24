<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\AuditItemController;
use App\Modules\Audit\Http\Controllers\AuditReviewController;
use App\Modules\Audit\Http\Controllers\AuditUploadController;
use Illuminate\Support\Facades\Route;

/*
 * Routes for Audit Upload, Review, and Gatekeeper (PRD v1.1 Batch 2).
 */
Route::middleware(['auth', 'village', 'role:desa'])->group(function () {
    Route::get('/audits/create', [AuditUploadController::class, 'create'])->name('audits.create');
    Route::post('/audits', [AuditUploadController::class, 'store'])->name('audits.store');
    Route::get('/audits/{audit}', [AuditReviewController::class, 'show'])->name('audits.show');
    Route::post('/audits/{audit}/retry', [AuditUploadController::class, 'retry'])->name('audits.retry');
    Route::post('/audits/{audit}/replace-file', [AuditUploadController::class, 'replaceFile'])->name('audits.replace_file');
    Route::delete('/audits/{audit}', [AuditUploadController::class, 'destroy'])->name('audits.destroy');
    Route::get('/audits/{audit}/document', [AuditReviewController::class, 'document'])->name('audits.document');
    Route::post('/audits/{audit}/publish', [AuditReviewController::class, 'publish'])->name('audits.publish');
    Route::post('/audits/{audit}/ack-total', [AuditReviewController::class, 'ackTotal'])->name('audits.ack_total');

    Route::post('/audits/{audit}/items', [AuditItemController::class, 'store'])->name('audits.items.store');
    Route::post('/audits/{audit}/items/{item}/confirm', [AuditItemController::class, 'confirm'])->name('audits.items.confirm');
    Route::patch('/audits/{audit}/items/{item}', [AuditItemController::class, 'update'])->name('audits.items.update');
    Route::delete('/audits/{audit}/items/{item}', [AuditItemController::class, 'destroy'])->name('audits.items.destroy');
});
