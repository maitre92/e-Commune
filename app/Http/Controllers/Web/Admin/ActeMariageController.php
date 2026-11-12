<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreActeMariageRequest;
use App\Http\Requests\Web\UpdateCivilSettingsRequest;
use App\Models\ActeMariage;
use App\Models\ExtraitMariage;
use App\Services\Audit\ActivityLogger;
use App\Services\EtatCivil\ActeMariageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Actes de mariage, sur le même modèle que les actes de naissance : une page où l'on remplit directement le modèle
 * d'extrait d'acte de mariage, puis « Enregistrer et imprimer » ; une recherche (n° d'acte, nom d'un époux)
 * retrouve un acte pour réimprimer son extrait en un clic. Cloisonné par mairie, tout est journalisé.
 */
class ActeMariageController extends Controller
{
    public function index(Request $request, ActeMariageService $actes): View
    {
        Gate::authorize('viewAny', ActeMariage::class);

        $term = trim((string) $request->query('q', ''));

        return view('actes-mariage.index', [
            'acte' => $request->filled('acte') ? ActeMariage::with('extraits')->findOrFail((int) $request->query('acte')) : null,
            'term' => $term,
            'results' => $term === '' ? collect() : $actes->search($term),
            'recent' => ActeMariage::withCount('extraits')->visibleFor($request->user())->latest('id')->limit(10)->get(),
            'mairie' => $request->user()->mairie,
        ]);
    }

    public function store(StoreActeMariageRequest $request, ActeMariageService $actes): RedirectResponse
    {
        [$acte, $extrait] = $actes->save($request);

        return $this->done($acte, $extrait, 'enregistré');
    }

    public function update(StoreActeMariageRequest $request, ActeMariage $acteMariage, ActeMariageService $actes): RedirectResponse
    {
        [, $extrait] = $actes->save($request, $acteMariage);

        return $this->done($acteMariage, $extrait, 'mis à jour');
    }

    public function deliver(Request $request, ActeMariage $acteMariage, ActeMariageService $actes): RedirectResponse
    {
        Gate::authorize('deliver', $acteMariage);

        return $this->done($acteMariage, $actes->deliver($acteMariage, $request->user()), 'retrouvé');
    }

    /** Aperçu de l'extrait (bouton œil) : rien n'est délivré ni encaissé. */
    public function preview(ActeMariage $acteMariage, ActeMariageService $actes): View
    {
        Gate::authorize('view', $acteMariage);

        return view('actes-mariage.extrait', ['extrait' => $actes->previewExtrait($acteMariage), 'acte' => $acteMariage]);
    }

    /** Réglages d'état civil de la mairie (les mêmes que pour les naissances). */
    public function updateSettings(UpdateCivilSettingsRequest $request): RedirectResponse
    {
        $request->user()->mairie->update($request->validated());

        return redirect()->route('actes-mariage.index')->with('status', 'Réglages de l\'état civil enregistrés.');
    }

    public function destroy(ActeMariage $acteMariage): RedirectResponse
    {
        Gate::authorize('delete', $acteMariage);

        $acteMariage->delete();

        return redirect()->route('actes-mariage.index')->with('status', "Acte de mariage {$acteMariage->activityLabel()} supprimé.");
    }

    /** Extrait imprimable (A4), à l'identique de sa délivrance ; chaque impression est journalisée. */
    public function extrait(ExtraitMariage $extrait, ActivityLogger $logger): View
    {
        $acte = $extrait->acte;
        Gate::authorize('view', $acte);

        $logger->log('actes-mariage.extract_printed', 'actes-mariage', "Extrait imprimé — acte de mariage {$acte->activityLabel()}", $acte);

        return view('actes-mariage.extrait', ['extrait' => $extrait, 'acte' => $acte]);
    }

    private function done(ActeMariage $acte, ?ExtraitMariage $extrait, string $verb): RedirectResponse
    {
        $redirect = redirect()->route('actes-mariage.index', ['acte' => $acte->getKey()]);

        return $extrait
            ? $redirect->with('status', "Acte de mariage {$acte->activityLabel()} {$verb} : impression de l'extrait.")->with('print_extrait', route('actes-mariage.extrait', $extrait))
            : $redirect->with('status', "Acte de mariage {$acte->activityLabel()} {$verb}.");
    }
}
