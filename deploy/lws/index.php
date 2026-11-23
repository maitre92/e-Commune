<?php

// Front controller pour LWS : le code Laravel est HORS de public_html (dans ~/e-commune_app),
// seul le contenu de public/ est servi depuis ~/public_html/e-commune.

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// ~/public_html/e-commune → ~ (= /home/c2823388c)
$appPath = dirname(__DIR__, 2).'/e-commune_app';

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appPath.'/bootstrap/app.php';

// public_path() doit pointer vers le dossier réellement servi (assets, lien storage).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
