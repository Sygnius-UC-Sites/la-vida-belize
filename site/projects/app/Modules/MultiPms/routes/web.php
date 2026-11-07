<?php

use App\Modules\MultiPms\Http\Controllers\Admin\PmsSyncController;
use App\Modules\MultiPms\Http\Controllers\Admin\PmsPropertyController;

// Admin routes — auto-prefixed with /admin/multi-pms by AbstractModuleServiceProvider
// and named admin.multi-pms.*

Route::get('/',                    [PmsPropertyController::class, 'index'])->name('properties.index');
Route::get('/properties',          [PmsPropertyController::class, 'index'])->name('properties.list');
Route::get('/properties/{id}',     [PmsPropertyController::class, 'show'])->name('properties.show');
Route::post('/sync',               [PmsSyncController::class, 'sync'])->name('sync');
Route::post('/sync/{pms}',         [PmsSyncController::class, 'syncOne'])->name('sync.one');
