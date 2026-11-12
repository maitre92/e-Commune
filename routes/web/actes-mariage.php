<?php

use App\Http\Controllers\Web\Admin\ActeMariageController;
use Illuminate\Support\Facades\Route;

// Actes de mariage. Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'channel:web', 'password.changed'])->group(function () {
    Route::get('/actes-mariage', [ActeMariageController::class, 'index'])->middleware('permission:actes-mariage.view')->name('actes-mariage.index');
    Route::post('/actes-mariage', [ActeMariageController::class, 'store'])->middleware('permission:actes-mariage.create')->name('actes-mariage.store');
    Route::put('/actes-mariage/reglages', [ActeMariageController::class, 'updateSettings'])->middleware('permission:actes-mariage.configure')->name('actes-mariage.settings');
    Route::get('/actes-mariage/{acteMariage}/apercu', [ActeMariageController::class, 'preview'])->middleware('permission:actes-mariage.view')->name('actes-mariage.preview');
    Route::post('/actes-mariage/{acteMariage}/extrait', [ActeMariageController::class, 'deliver'])->middleware('permission:actes-mariage.deliver')->name('actes-mariage.deliver');
    Route::put('/actes-mariage/{acteMariage}', [ActeMariageController::class, 'update'])->middleware('permission:actes-mariage.update')->name('actes-mariage.update');
    Route::delete('/actes-mariage/{acteMariage}', [ActeMariageController::class, 'destroy'])->middleware('permission:actes-mariage.delete')->name('actes-mariage.destroy');
    Route::get('/actes-mariage/extraits/{extrait}', [ActeMariageController::class, 'extrait'])->middleware('permission:actes-mariage.view')->name('actes-mariage.extrait');
});
