<?php

use App\Http\Controllers\Api\V1\ActeMariageController;
use App\Http\Controllers\Api\V1\ActeNaissanceController;
use App\Http\Controllers\Api\V1\CaisseController;
use App\Http\Controllers\Api\V1\EtatCivilSettingsController;
use App\Http\Controllers\Api\V1\VignetteController;
use Illuminate\Support\Facades\Route;

// Les deux guichets de l'application mobile. Pile : auth:sanctum -> active -> channel:api -> throttle -> mot de
// passe changé -> permission:module.action (jamais role:).
Route::middleware(['auth:sanctum', 'active', 'channel:api', 'throttle:api', 'password.changed'])->group(function () {
    Route::get('/vignettes/guichet', [VignetteController::class, 'lookup'])->middleware('permission:demandes-vgt.create')->name('api.vignettes.lookup');
    Route::post('/vignettes/guichet', [VignetteController::class, 'store'])->middleware('permission:demandes-vgt.create')->name('api.vignettes.store');
    Route::put('/vignettes/{demandeId}/correction', [VignetteController::class, 'correct'])->whereNumber('demandeId')->middleware('permission:demandes-vgt.update')->name('api.vignettes.correct');
    Route::get('/vignettes', [VignetteController::class, 'index'])->middleware('permission:demandes-vgt.view')->name('api.vignettes.index');
    Route::get('/vignettes/{demandeId}/carte', [VignetteController::class, 'card'])->whereNumber('demandeId')->middleware('permission:demandes-vgt.view')->name('api.vignettes.card');

    Route::get('/etat-civil/reglages', [EtatCivilSettingsController::class, 'show'])->middleware('permission:actes-naissance.configure|actes-mariage.configure')->name('api.etat-civil.settings');
    Route::put('/etat-civil/reglages', [EtatCivilSettingsController::class, 'update'])->middleware('permission:actes-naissance.configure|actes-mariage.configure')->name('api.etat-civil.settings.update');
    Route::get('/actes', [ActeNaissanceController::class, 'index'])->middleware('permission:actes-naissance.view')->name('api.actes.index');
    Route::get('/actes/nouveau', [ActeNaissanceController::class, 'defaults'])->middleware('permission:actes-naissance.view')->name('api.actes.defaults');
    Route::get('/actes/{acteNaissance}', [ActeNaissanceController::class, 'show'])->middleware('permission:actes-naissance.view')->name('api.actes.show');
    Route::post('/actes', [ActeNaissanceController::class, 'store'])->middleware('permission:actes-naissance.create')->name('api.actes.store');
    Route::get('/actes/{acteNaissance}/apercu', [ActeNaissanceController::class, 'preview'])->middleware('permission:actes-naissance.view')->name('api.actes.preview');
    Route::post('/actes/{acteNaissance}/extrait', [ActeNaissanceController::class, 'deliver'])->middleware('permission:actes-naissance.deliver')->name('api.actes.deliver');
    Route::put('/actes/{acteNaissance}', [ActeNaissanceController::class, 'update'])->middleware('permission:actes-naissance.update')->name('api.actes.update');
    Route::get('/mariages', [ActeMariageController::class, 'index'])->middleware('permission:actes-mariage.view')->name('api.mariages.index');
    Route::get('/mariages/{acteMariage}', [ActeMariageController::class, 'show'])->middleware('permission:actes-mariage.view')->name('api.mariages.show');
    Route::post('/mariages', [ActeMariageController::class, 'store'])->middleware('permission:actes-mariage.create')->name('api.mariages.store');
    Route::get('/mariages/{acteMariage}/apercu', [ActeMariageController::class, 'preview'])->middleware('permission:actes-mariage.view')->name('api.mariages.preview');
    Route::post('/mariages/{acteMariage}/extrait', [ActeMariageController::class, 'deliver'])->middleware('permission:actes-mariage.deliver')->name('api.mariages.deliver');
    Route::put('/mariages/{acteMariage}', [ActeMariageController::class, 'update'])->middleware('permission:actes-mariage.update')->name('api.mariages.update');
    Route::get('/extraits-mariage/{extrait}', [ActeMariageController::class, 'extrait'])->middleware('permission:actes-mariage.view')->name('api.extraits-mariage.show');
    Route::get('/caisse', [CaisseController::class, 'index'])->middleware('permission:caisse.view_own')->name('api.caisse.index');
    Route::post('/caisse/versements', [CaisseController::class, 'receive'])->middleware('permission:caisse.receive')->name('api.caisse.receive');
    Route::get('/extraits/{extrait}', [ActeNaissanceController::class, 'extrait'])->middleware('permission:actes-naissance.view')->name('api.extraits.show');
});
