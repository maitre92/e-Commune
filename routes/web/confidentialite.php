<?php

use Illuminate\Support\Facades\Route;

// Politique de confidentialité publique (exigée par le Google Play Store pour l'application mobile) : sans compte,
// limitée par IP. Liste blanche du test d'architecture.
Route::view('/confidentialite', 'confidentialite')->middleware('throttle:public')->name('privacy');
