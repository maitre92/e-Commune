<?php

// Routes API (jetons Bearer, sans session ni CSRF). Préfixe /api/v1 (bootstrap/app.php). Un fichier par module dans
// routes/api/v1/*.php, chargés dans l'ordre alphabétique. Utilisé par l'application mobile e-Commune (mobile/).

$files = glob(__DIR__.'/api/v1/*.php') ?: [];
sort($files);

foreach ($files as $file) {
    require $file;
}
