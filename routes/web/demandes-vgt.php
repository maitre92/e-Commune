<?php

use App\Http\Controllers\Web\Admin\DemandeVgtController;
use App\Http\Controllers\Web\Admin\GuichetVignetteController;
use App\Http\Controllers\Web\Admin\TarifVgtController;
use Illuminate\Support\Facades\Route;

// Vignettes. Toutes les routes portent permission:module.action (jamais role:, ARCHITECTURE §7).
Route::middleware(['auth', 'active', 'channel:web', 'password.changed'])->group(function () {
    Route::get('/demandes-vgt', [DemandeVgtController::class, 'index'])->middleware('permission:demandes-vgt.view')->name('demandes-vgt.index');
    Route::get('/demandes-vgt/{demandeId}/carte', [DemandeVgtController::class, 'card'])->whereNumber('demandeId')->middleware('permission:demandes-vgt.view')->name('demandes-vgt.card');
    Route::get('/vignettes/modeles/{template}', [DemandeVgtController::class, 'modelPreview'])->middleware('permission:demandes-vgt.card_settings|mairies.update')->name('demandes-vgt.model-preview');
    Route::put('/vignettes/annees/{year}', [DemandeVgtController::class, 'updateYearModel'])->whereNumber('year')->middleware('permission:demandes-vgt.card_settings')->name('demandes-vgt.year-model');
    Route::put('/demandes-vgt/{demandeVgt}/correction', [GuichetVignetteController::class, 'correct'])->middleware('permission:demandes-vgt.update')->name('demandes-vgt.correct');
    Route::post('/demandes-vgt/guichet', [GuichetVignetteController::class, 'store'])->middleware('permission:demandes-vgt.create')->name('demandes-vgt.guichet');
    Route::post('/demandes-vgt', [DemandeVgtController::class, 'store'])->middleware('permission:demandes-vgt.create')->name('demandes-vgt.store');
    Route::put('/demandes-vgt/{demandeVgt}', [DemandeVgtController::class, 'update'])->middleware('permission:demandes-vgt.update')->name('demandes-vgt.update');
    Route::put('/demandes-vgt/{demandeVgt}/paiement', [DemandeVgtController::class, 'confirmPaiement'])->middleware('permission:demandes-vgt.confirm_payment')->name('demandes-vgt.confirm-payment');
    Route::put('/demandes-vgt/{demandeVgt}/retrait', [DemandeVgtController::class, 'confirmRetrait'])->middleware('permission:demandes-vgt.confirm_retrait')->name('demandes-vgt.confirm-retrait');

    Route::get('/demandes-vgt/guichet/moto', [GuichetVignetteController::class, 'lookup'])->middleware('permission:demandes-vgt.create')->name('demandes-vgt.guichet-lookup');

    Route::put('/tarifs-vgt', [TarifVgtController::class, 'save'])->middleware('permission:demandes-vgt.manage_tarifs')->name('tarifs-vgt.save');
});
