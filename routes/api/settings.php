<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SettingsController;

/*
|--------------------------------------------------------------------------
| Settings API Routes
|--------------------------------------------------------------------------
| Routes untuk settings management
*/

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::get('/rates', [SettingsController::class, 'getRates'])->name('rates');
    Route::get('/{key}', [SettingsController::class, 'show'])->name('show');
    Route::put('/{key}', [SettingsController::class, 'update'])->name('update');
    Route::post('/clear-cache', [SettingsController::class, 'clearCache'])->name('clear-cache');
});