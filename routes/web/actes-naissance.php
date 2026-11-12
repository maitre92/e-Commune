<?php

use App\Http\Controllers\Web\Admin\ActeNaissanceController;
use Illuminate\Support\Facades\Route;

// Actes de naissance. Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'channel:web', 'password.changed'])->group(function () {
    Route::get('/actes-naissance', [ActeNaissanceController::class, 'index'])->middleware('permission:actes-naissance.view')->name('actes-naissance.index');
    Route::post('/actes-naissance', [ActeNaissanceController::class, 'store'])->middleware('permission:actes-naissance.create')->name('actes-naissance.store');
    Route::put('/actes-naissance/reglages', [ActeNaissanceController::class, 'updateSettings'])->middleware('permission:actes-naissance.configure')->name('actes-naissance.settings');
    Route::get('/actes-naissance/{acteNaissance}/apercu', [ActeNaissanceController::class, 'preview'])->middleware('permission:actes-naissance.view')->name('actes-naissance.preview');
    Route::post('/actes-naissance/{acteNaissance}/extrait', [ActeNaissanceController::class, 'deliver'])->middleware('permission:actes-naissance.deliver')->name('actes-naissance.deliver');
    Route::put('/actes-naissance/{acteNaissance}', [ActeNaissanceController::class, 'update'])->middleware('permission:actes-naissance.update')->name('actes-naissance.update');
    Route::delete('/actes-naissance/{acteNaissance}', [ActeNaissanceController::class, 'destroy'])->middleware('permission:actes-naissance.delete')->name('actes-naissance.destroy');
    Route::get('/actes-naissance/extraits/{extrait}', [ActeNaissanceController::class, 'extrait'])->middleware('permission:actes-naissance.view')->name('actes-naissance.extrait');
});
