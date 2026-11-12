<?php

use App\Http\Controllers\Web\Admin\CaisseController;
use Illuminate\Support\Facades\Route;

// Caisse de la mairie. Toutes les routes portent permission:module.action (jamais role:).
Route::middleware(['auth', 'active', 'channel:web', 'password.changed'])->group(function () {
    Route::get('/caisse', [CaisseController::class, 'index'])->middleware('permission:caisse.view_own')->name('caisse.index');
    Route::post('/caisse/versements', [CaisseController::class, 'receive'])->middleware('permission:caisse.receive')->name('caisse.receive');
    Route::get('/caisse/etat', [CaisseController::class, 'etat'])->middleware('permission:caisse.view')->name('caisse.etat');
});
