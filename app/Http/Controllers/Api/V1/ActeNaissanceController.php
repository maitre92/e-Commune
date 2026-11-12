<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreActeNaissanceRequest;
use App\Models\ActeNaissance;
use App\Models\ExtraitNaissance;
use App\Services\Audit\ActivityLogger;
use App\Services\EtatCivil\ActeNaissanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Actes de naissance de l'application mobile : mêmes règles que le Web (ActeNaissanceService). L'application
 * affiche le modèle d'extrait à remplir, enregistre, puis imprime l'extrait renvoyé (HTML, `extrait_url`).
 * Les champs JSON portent les noms des colonnes (ceux du formulaire Web).
 */
class ActeNaissanceController extends Controller
{
    /** Recherche (n° d'acte, nom, prénom, NINA) ; sans recherche, les derniers actes saisis. */
    public function index(Request $request, ActeNaissanceService $actes): JsonResponse
    {
        Gate::authorize('viewAny', ActeNaissance::class);

        $term = trim((string) $request->query('q', ''));
        $list = $term === '' ? ActeNaissance::visibleFor($request->user())->latest('id')->limit(20)->get() : $actes->search($term);

        return response()->json(['data' => $list->map(fn (ActeNaissance $acte) => $this->summary($acte))->all()]);
    }

    /** Réglages d'état civil de la mairie (repris automatiquement) : l'application les affiche, sans les ressaisir. */
    public function defaults(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ActeNaissance::class);

        $mairie = $request->user()->mairie;

        return response()->json(['data' => [
            'pret' => $mairie?->civilReady() ?? false,
            'centre' => $mairie?->civil_centre_name,
            'officier' => $mairie?->civil_officer_name,
            'localite_par_defaut' => $mairie?->civil_birth_place,
        ]]);
    }

    /** Aperçu de l'extrait (bouton œil de l'application) : rien n'est délivré ni encaissé. */
    public function preview(ActeNaissance $acteNaissance, ActeNaissanceService $actes): View
    {
        Gate::authorize('view', $acteNaissance);

        return view('actes-naissance.extrait', ['extrait' => $actes->previewExtrait($acteNaissance), 'acte' => $acteNaissance, 'fromApp' => true]);
    }

    /** Réimpression en un clic : extrait certifié par l'officier signataire réglé, daté du jour. */
    public function deliver(Request $request, ActeNaissance $acteNaissance, ActeNaissanceService $actes): JsonResponse
    {
        Gate::authorize('deliver', $acteNaissance);

        $extrait = $actes->deliver($acteNaissance, $request->user());

        return response()->json(['extrait_url' => route('api.extraits.show', $extrait)], 201);
    }

    public function show(ActeNaissance $acteNaissance): JsonResponse
    {
        Gate::authorize('view', $acteNaissance);

        return response()->json(['data' => $this->full($acteNaissance->load('extraits'))]);
    }

    public function store(StoreActeNaissanceRequest $request, ActeNaissanceService $actes): JsonResponse
    {
        [$acte, $extrait] = $actes->save($request);

        return response()->json($this->saved($acte, $extrait), 201);
    }

    public function update(StoreActeNaissanceRequest $request, ActeNaissance $acteNaissance, ActeNaissanceService $actes): JsonResponse
    {
        [$acte, $extrait] = $actes->save($request, $acteNaissance);

        return response()->json($this->saved($acte, $extrait));
    }

    /** Extrait imprimable (HTML A4), journalisé comme sur le Web. */
    public function extrait(ExtraitNaissance $extrait, ActivityLogger $logger): View
    {
        $acte = $extrait->acte;
        Gate::authorize('view', $acte);

        $logger->log('actes-naissance.extract_printed', 'actes-naissance', "Extrait imprimé — acte {$acte->activityLabel()}", $acte);

        // Imprimé par le téléphone, qui ajoute ses propres marges : mise en page qui tient dans une page avec elles.
        return view('actes-naissance.extrait', ['extrait' => $extrait, 'acte' => $acte, 'fromApp' => true]);
    }

    /** @return array<string, mixed> */
    private function saved(ActeNaissance $acte, ?ExtraitNaissance $extrait): array
    {
        return [
            'data' => $this->full($acte->load('extraits')),
            'extrait_url' => $extrait ? route('api.extraits.show', $extrait) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function summary(ActeNaissance $acte): array
    {
        return [
            'id' => $acte->id,
            'act_number' => $acte->act_number,
            'act_year' => $acte->act_year,
            'nom_complet' => $acte->fullName(),
            'birth_date' => $acte->birth_date->toDateString(),
            'centre' => $acte->centreLabel(),
        ];
    }

    /** @return array<string, mixed> */
    private function full(ActeNaissance $acte): array
    {
        return $this->plain($acte->only([
            'id', 'region', 'cercle', 'arrondissement', 'commune', 'centre_type', 'centre_name', 'act_number', 'act_year',
            'nina', 'first_names', 'last_name', 'birth_date', 'birth_time', 'birth_place', 'gender',
            'father_first_names', 'father_last_name', 'father_profession', 'father_domicile',
            'mother_first_names', 'mother_last_name', 'mother_profession', 'mother_domicile',
            'officer_name', 'officer_title', 'drawn_up_at',
        ])) + [
            'extraits' => $acte->extraits->sortByDesc('id')->values()->map(fn (ExtraitNaissance $extrait) => [
                'id' => $extrait->id,
                'delivered_at' => $extrait->delivered_at->toDateString(),
                'officer_name' => $extrait->officer_name,
                'url' => route('api.extraits.show', $extrait),
            ])->all(),
        ];
    }

    /**
     * Enums et dates en valeurs simples, comme les attend le formulaire.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn ($value) => match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        }, $values);
    }
}
