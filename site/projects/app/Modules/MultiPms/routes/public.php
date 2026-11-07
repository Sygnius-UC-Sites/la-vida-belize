<?php

use App\Modules\MultiPms\Http\Controllers\AvailabilityController;

// Real-time availability endpoint — used by the property page datepicker
Route::get('/pms-availability', [AvailabilityController::class, 'get'])->name('pms-availability');
