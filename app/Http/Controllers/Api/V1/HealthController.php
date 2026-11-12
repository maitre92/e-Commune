<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\HomeMairie;
use Illuminate\Http\JsonResponse;

/** Disponibilité du serveur et mairie de l'installation (affichée sur l'écran de connexion de l'application). */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'time' => now()->toIso8601String(), 'mairie' => HomeMairie::current()?->name]);
    }
}
