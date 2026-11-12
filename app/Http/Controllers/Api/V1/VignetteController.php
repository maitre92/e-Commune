<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DemandeVgtStatus;
use App\Enums\VgtLateMode;
use App\Exceptions\VignetteAlreadyIssuedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\GuichetVignetteRequest;
use App\Models\DemandeVgt;
use App\Models\Moto;
use App\Models\TarifVgt;
use App\Services\Vgt\DemandeVgtPricing;
use App\Services\Vgt\GuichetVignette;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Guichet vignette de l'application mobile : mêmes règles que le Web (GuichetVignette) — le CH suffit, la
 * délivrance renvoie la carte à imprimer depuis le téléphone (HTML, `carte_url`, modèle de l'année).
 */
class VignetteController extends Controller
{
    /**
     * Ce qu'il faut pour le guichet : le montant de l'année, les catégories de tarif (moto inconnue) et, si un CH
     * est donné, la moto déjà connue.
     */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('create', DemandeVgt::class);

        $cadre = Moto::normalizeChassis($request->query('cadre'));
        $moto = $cadre === '' ? null : Moto::with('proprietaire')->where('chassis_number', $cadre)->first();
        $year = (int) date('Y');
        $mairie = $request->user()->mairie;
        $categories = $mairie ? TarifVgt::gridFor($mairie->id, activeOnly: true) : collect();
        $known = $moto ? $categories->firstWhere('type_or_brand', $moto->type_or_brand) : null;
        $years = range($year + 1, $year - 5);

        return response()->json([
            'cadre' => $cadre,
            'moto' => $moto ? $this->moto($moto) : null,
            'annee' => $year,
            'montant' => $known?->amount,
            'categorie' => $known?->type_or_brand,
            'categories' => $categories->map(fn (TarifVgt $tarif) => ['nom' => $tarif->type_or_brand, 'tranche' => $tarif->rangeLabel(), 'montant' => $tarif->amount])->values()->all(),
            // Pénalité de retard réglée par la mairie : années en retard aujourd'hui, et comment elle se calcule.
            'retard' => [
                'mode' => ($mairie?->vgt_late_mode ?? VgtLateMode::Double)->value,
                'libelle' => ($mairie?->vgt_late_mode ?? VgtLateMode::Double)->label(),
                'montant_fixe' => (int) ($mairie?->vgt_late_amount ?? 0),
                'date_limite' => $mairie?->vgtDeadlineLabel(),
                'annees_en_retard' => $mairie ? array_values(array_filter($years, fn (int $y) => DemandeVgtPricing::isLate($mairie, $y))) : [],
            ],
        ]);
    }

    public function store(GuichetVignetteRequest $request, GuichetVignette $guichet): JsonResponse
    {
        try {
            $demande = $guichet->issue($request);
        } catch (VignetteAlreadyIssuedException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'already_issued'], 409);
        }

        return response()->json(['data' => $this->vignette($demande->load('moto.proprietaire', 'mairie'))], 201);
    }

    /** Corrige une vignette délivrée (CH mal recopié, détenteur) ; la carte corrigée est renvoyée pour réimpression. */
    public function correct(Request $request, int $demandeId, GuichetVignette $guichet): JsonResponse
    {
        $demande = DemandeVgt::visibleFor($request->user())->findOrFail($demandeId);
        Gate::authorize('correct', $demande);
        $data = $request->validate(['chassis_number' => ['required', 'string', 'max:50'], 'holder_name' => ['nullable', 'string', 'max:150'], 'holder_phone' => ['nullable', 'string', 'max:30']]);

        $demande = $guichet->correct($demande, $data['chassis_number'], $data['holder_name'] ?? null, $data['holder_phone'] ?? null);

        return response()->json(['data' => $this->vignette($demande->load('moto.proprietaire', 'mairie'))]);
    }

    /** Dernières vignettes délivrées par la mairie (réimpression). */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', DemandeVgt::class);

        $demandes = DemandeVgt::with(['moto.proprietaire', 'mairie'])->visibleFor(request()->user())
            ->whereIn('status', [DemandeVgtStatus::Payee->value, DemandeVgtStatus::Retiree->value])
            ->latest('id')->limit(30)->get();

        return response()->json(['data' => $demandes->map(fn (DemandeVgt $demande) => $this->vignette($demande))->all()]);
    }

    /** Carte imprimable (HTML, recto + verso) dans le modèle de son année. */
    public function card(int $demandeId): View
    {
        Gate::authorize('viewAny', DemandeVgt::class);

        $demande = DemandeVgt::with(['moto.proprietaire', 'mairie'])->visibleFor(request()->user())->findOrFail($demandeId);
        abort_unless(in_array($demande->status, [DemandeVgtStatus::Payee, DemandeVgtStatus::Retiree], true), 404);

        return view('demandes-vgt.card', ['demande' => $demande, 'template' => $demande->cardTemplate(), 'face' => 'both', 'preview' => false]);
    }

    /** @return array<string, mixed> */
    private function moto(Moto $moto): array
    {
        return [
            'id' => $moto->id,
            'cadre' => $moto->chassis_number,
            'matricule' => $moto->plate_number,
            'genre' => $moto->type_or_brand,
            'couleur' => $moto->color,
            'derniere_vignette' => $moto->vgt_year,
            'a_jour' => $moto->isVgtCurrent(),
            'proprietaire' => $moto->proprietaire ? ['nom' => $moto->proprietaire->fullName(), 'telephone' => $moto->proprietaire->phone] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function vignette(DemandeVgt $demande): array
    {
        return [
            'id' => $demande->id,
            'annee' => $demande->vgt_year,
            'montant' => ['base' => $demande->base_amount, 'majoration' => $demande->surcharge_amount, 'total' => $demande->totalAmount()],
            'categorie' => $demande->category_label ?? $demande->moto?->type_or_brand,
            'detenteur' => $demande->holderName() ?: null,
            'telephone_detenteur' => $demande->holderPhone() ?: null,
            'modele' => $demande->cardTemplate()->label(),
            'delivree_le' => $demande->created_at->toIso8601String(),
            'moto' => $this->moto($demande->moto),
            'carte_url' => route('api.vignettes.card', $demande->id),
        ];
    }
}
