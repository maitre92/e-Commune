<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecetteType;
use App\Http\Controllers\Controller;
use App\Models\Recette;
use App\Services\Caisse\CaisseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Onglet « Caisse » de l'application. L'agent voit ses recettes du jour et ce qu'il doit verser ; le maire (ou le
 * secrétaire général, ou le caissier) voit toute la caisse de la mairie et reçoit les versements.
 */
class CaisseController extends Controller
{
    public function index(Request $request, CaisseService $caisse): JsonResponse
    {
        $user = $request->user();
        ['full' => $full, 'recettes' => $recettes, 'a_verser' => $aVerser, 'types' => $types] = $caisse->etat($user, now());

        return response()->json([
            'toute_la_mairie' => $full,
            'peut_recevoir' => $user->can('caisse.receive'),
            'types' => $types->map(fn (RecetteType $type) => $type->value)->all(),
            'aujourd_hui' => [
                'montant' => (int) $recettes->sum('amount'),
                'nombre' => $recettes->count(),
                'vignettes' => $recettes->where('type', RecetteType::Vignette)->count(),
                'extraits' => $recettes->where('type', RecetteType::Extrait)->count(),
                'extraits_mariage' => $recettes->where('type', RecetteType::ExtraitMariage)->count(),
            ],
            'a_verser' => $aVerser->map(fn (array $row) => [
                'agent_id' => $row['agent']?->id,
                'agent' => $row['agent']?->name,
                'fonction' => $row['agent']?->roleName()?->label(),
                'nombre' => $row['nombre'],
                'montant' => $row['total'],
            ])->values()->all(),
            'recettes' => $recettes->take(50)->map(fn (Recette $recette) => [
                'quittance' => $recette->quittance(),
                'heure' => $recette->encaissee_at->format('H:i'),
                'objet' => $recette->label,
                'agent' => $recette->agent?->name,
                'montant' => $recette->amount,
                'versee' => $recette->versement_id !== null,
            ])->values()->all(),
        ]);
    }

    /** Le maire, le secrétaire général ou le caissier reçoit l'argent d'un agent de sa mairie. */
    public function receive(Request $request, CaisseService $caisse): JsonResponse
    {
        // L'agent est retrouvé dans les recettes de la mairie (cloisonnées) : jamais celui d'une autre mairie.
        $agent = Recette::query()->where('user_id', $request->integer('agent_id'))->first()?->agent;
        abort_if($agent === null, 404);

        $versement = $caisse->recevoir($agent, $request->user());

        return response()->json(['message' => "Versement de {$agent->name} reçu : ".number_format($versement->amount, 0, ',', ' ').' FCFA.', 'montant' => $versement->amount], 201);
    }
}
