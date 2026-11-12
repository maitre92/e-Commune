<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreActeMariageRequest;
use App\Models\ActeMariage;
use App\Models\ExtraitMariage;
use App\Services\Audit\ActivityLogger;
use App\Services\EtatCivil\ActeMariageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Actes de mariage de l'application mobile : mêmes règles que le Web (ActeMariageService). Les champs JSON portent
 * les noms des colonnes (ceux du formulaire Web) ; l'extrait est renvoyé en HTML à imprimer (`extrait_url`).
 */
class ActeMariageController extends Controller
{
    /** Recherche (n° d'acte, nom ou prénom d'un époux) ; sans recherche, les derniers actes saisis. */
    public function index(Request $request, ActeMariageService $actes): JsonResponse
    {
        Gate::authorize('viewAny', ActeMariage::class);

        $term = trim((string) $request->query('q', ''));
        $list = $term === '' ? ActeMariage::visibleFor($request->user())->latest('id')->limit(20)->get() : $actes->search($term);

        return response()->json([
            'data' => $list->map(fn (ActeMariage $acte) => [
                'id' => $acte->id,
                'act_number' => $acte->act_number,
                'act_year' => $acte->act_year,
                'epoux' => $acte->husbandName(),
                'epouse' => $acte->wifeName(),
                'celebrated_at' => $acte->celebrated_at->toDateString(),
                'centre' => $acte->centreLabel(),
            ])->all(),
            'tous_les_actes' => $request->user()->can('actes-mariage.view_all'),
        ]);
    }

    public function show(ActeMariage $acteMariage): JsonResponse
    {
        Gate::authorize('view', $acteMariage);

        return response()->json(['data' => $this->full($acteMariage->load('extraits'))]);
    }

    public function store(StoreActeMariageRequest $request, ActeMariageService $actes): JsonResponse
    {
        [$acte, $extrait] = $actes->save($request);

        return response()->json($this->saved($acte, $extrait), 201);
    }

    public function update(StoreActeMariageRequest $request, ActeMariage $acteMariage, ActeMariageService $actes): JsonResponse
    {
        [$acte, $extrait] = $actes->save($request, $acteMariage);

        return response()->json($this->saved($acte, $extrait));
    }

    /** Délivrance en un clic : extrait certifié par l'officier signataire réglé, daté du jour, encaissé. */
    public function deliver(Request $request, ActeMariage $acteMariage, ActeMariageService $actes): JsonResponse
    {
        Gate::authorize('deliver', $acteMariage);

        return response()->json(['extrait_url' => route('api.extraits-mariage.show', $actes->deliver($acteMariage, $request->user()))], 201);
    }

    /** Aperçu de l'extrait (bouton œil) : rien n'est délivré ni encaissé. */
    public function preview(ActeMariage $acteMariage, ActeMariageService $actes): View
    {
        Gate::authorize('view', $acteMariage);

        return view('actes-mariage.extrait', ['extrait' => $actes->previewExtrait($acteMariage), 'acte' => $acteMariage, 'fromApp' => true]);
    }

    /** Extrait imprimable (HTML A4, mise en page du téléphone), journalisé comme sur le Web. */
    public function extrait(ExtraitMariage $extrait, ActivityLogger $logger): View
    {
        $acte = $extrait->acte;
        Gate::authorize('view', $acte);

        $logger->log('actes-mariage.extract_printed', 'actes-mariage', "Extrait imprimé — acte de mariage {$acte->activityLabel()}", $acte);

        return view('actes-mariage.extrait', ['extrait' => $extrait, 'acte' => $acte, 'fromApp' => true]);
    }

    /** @return array<string, mixed> */
    private function saved(ActeMariage $acte, ?ExtraitMariage $extrait): array
    {
        return [
            'data' => $this->full($acte->load('extraits')),
            'extrait_url' => $extrait ? route('api.extraits-mariage.show', $extrait) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function full(ActeMariage $acte): array
    {
        $values = $acte->only(['id', ...array_values(array_diff($acte->getFillable(), ['mairie_id', 'user_id']))]);

        return array_map(fn ($value) => match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        }, $values) + [
            'extraits' => $acte->extraits->sortByDesc('id')->values()->map(fn (ExtraitMariage $extrait) => [
                'id' => $extrait->id,
                'delivered_at' => $extrait->delivered_at->toDateString(),
                'url' => route('api.extraits-mariage.show', $extrait),
            ])->all(),
        ];
    }
}
