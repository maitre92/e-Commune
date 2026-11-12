<?php

namespace App\Http\Controllers\Web\Admin;

use App\Exceptions\VignetteAlreadyIssuedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\GuichetVignetteRequest;
use App\Models\DemandeVgt;
use App\Models\Moto;
use App\Services\Vgt\GuichetVignette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Guichet vignette (Web), en un clic : délivrance (GuichetVignette, partagé avec l'application mobile), puis la
 * carte s'imprime dans le modèle de la mairie.
 */
class GuichetVignetteController extends Controller
{
    public function store(GuichetVignetteRequest $request, GuichetVignette $guichet): RedirectResponse
    {
        $back = redirect()->route('demandes-vgt.index');

        try {
            $demande = $guichet->issue($request);
        } catch (VignetteAlreadyIssuedException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('status', "Vignette {$demande->vgt_year} de « {$demande->moto->identifier()} » délivrée : impression de la carte.")
            ->with('print_card', route('demandes-vgt.card', $demande->id));
    }

    /**
     * Guichet : ce que la mairie sait déjà d'un CH (cylindrée présélectionnée, dernière vignette), pendant la
     * saisie.
     */
    public function lookup(Request $request): JsonResponse
    {
        $moto = Moto::where('chassis_number', Moto::normalizeChassis($request->query('cadre')))->first();

        return response()->json([
            'connue' => $moto !== null,
            'categorie' => $moto?->type_or_brand,
            'derniere_vignette' => $moto?->vgt_year,
        ]);
    }

    /** Corrige une vignette délivrée (CH, détenteur) puis relance l'impression de la carte. */
    public function correct(Request $request, DemandeVgt $demandeVgt, GuichetVignette $guichet): RedirectResponse
    {
        Gate::authorize('correct', $demandeVgt);
        $data = $request->validate(['chassis_number' => ['required', 'string', 'max:50'], 'holder_name' => ['nullable', 'string', 'max:150'], 'holder_phone' => ['nullable', 'string', 'max:30']]);

        $demande = $guichet->correct($demandeVgt, $data['chassis_number'], $data['holder_name'] ?? null, $data['holder_phone'] ?? null);

        return redirect()->route('demandes-vgt.index')
            ->with('status', "Vignette corrigée : N° Engin {$demande->moto->chassis_number}. Réimpression de la carte.")
            ->with('print_card', route('demandes-vgt.card', $demande->id));
    }
}
