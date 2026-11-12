<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreActeNaissanceRequest;
use App\Http\Requests\Web\UpdateCivilSettingsRequest;
use App\Models\ActeNaissance;
use App\Models\ExtraitNaissance;
use App\Services\Audit\ActivityLogger;
use App\Services\EtatCivil\ActeNaissanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Actes de naissance, au plus simple : une seule page où l'on remplit directement le modèle d'extrait d'acte de
 * naissance, puis « Enregistrer et imprimer ». Une recherche (n° d'acte ou nom) recharge un acte déjà saisi dans
 * le modèle pour le réimprimer. Cloisonné par mairie (BelongsToMairie), tout est journalisé.
 */
class ActeNaissanceController extends Controller
{
    public function index(Request $request, ActeNaissanceService $actes): View
    {
        Gate::authorize('viewAny', ActeNaissance::class);

        $term = trim((string) $request->query('q', ''));
        $acte = $request->filled('acte') ? ActeNaissance::with('extraits')->findOrFail((int) $request->query('acte')) : null;

        $results = $term === '' ? collect() : $actes->search($term);

        return view('actes-naissance.index', [
            'acte' => $acte,
            'term' => $term,
            'results' => $results,
            // Derniers actes : les siens pour un agent, tous pour le maire. La recherche, elle, couvre tout le
            // registre de la mairie (pour délivrer l'extrait d'un acte saisi par un collègue).
            'recent' => ActeNaissance::withCount('extraits')->visibleFor($request->user())->latest('id')->limit(10)->get(),
            'defaults' => $actes->defaults(),
            'mairie' => $request->user()->mairie,
        ]);
    }

    public function store(StoreActeNaissanceRequest $request, ActeNaissanceService $actes): RedirectResponse
    {
        [$acte, $extrait] = $actes->save($request);

        return $this->done($acte, $extrait, 'enregistré');
    }

    public function update(StoreActeNaissanceRequest $request, ActeNaissance $acteNaissance, ActeNaissanceService $actes): RedirectResponse
    {
        [, $extrait] = $actes->save($request, $acteNaissance);

        return $this->done($acteNaissance, $extrait, 'mis à jour');
    }

    /** Réimpression en un clic depuis la recherche : extrait certifié par l'officier signataire réglé. */
    public function deliver(Request $request, ActeNaissance $acteNaissance, ActeNaissanceService $actes): RedirectResponse
    {
        Gate::authorize('deliver', $acteNaissance);

        $extrait = $actes->deliver($acteNaissance, $request->user());

        return $this->done($acteNaissance, $extrait, 'retrouvé');
    }

    /** Aperçu de l'extrait (bouton œil) : rien n'est délivré ni encaissé. */
    public function preview(ActeNaissance $acteNaissance, ActeNaissanceService $actes): View
    {
        Gate::authorize('view', $acteNaissance);

        return view('actes-naissance.extrait', ['extrait' => $actes->previewExtrait($acteNaissance), 'acte' => $acteNaissance]);
    }

    /** Réglages d'état civil de la mairie (saisis une fois). */
    public function updateSettings(UpdateCivilSettingsRequest $request): RedirectResponse
    {
        $request->user()->mairie->update($request->validated());

        return redirect()->route('actes-naissance.index')->with('status', 'Réglages de l\'état civil enregistrés.');
    }

    public function destroy(ActeNaissance $acteNaissance): RedirectResponse
    {
        Gate::authorize('delete', $acteNaissance);

        $acteNaissance->delete();

        return redirect()->route('actes-naissance.index')->with('status', "Acte de naissance {$acteNaissance->activityLabel()} supprimé.");
    }

    /**
     * Extrait imprimable (A4), à l'identique de sa délivrance. Chaque impression est journalisée : un extrait
     * d'acte est un document officiel.
     */
    public function extrait(ExtraitNaissance $extrait, ActivityLogger $logger): View
    {
        $acte = $extrait->acte;
        Gate::authorize('view', $acte);

        $logger->log('actes-naissance.extract_printed', 'actes-naissance', "Extrait imprimé — acte {$acte->activityLabel()}", $acte);

        return view('actes-naissance.extrait', ['extrait' => $extrait, 'acte' => $acte]);
    }

    private function done(ActeNaissance $acte, ?ExtraitNaissance $extrait, string $verb): RedirectResponse
    {
        $redirect = redirect()->route('actes-naissance.index', ['acte' => $acte->getKey()]);

        return $extrait
            ? $redirect->with('status', "Acte {$acte->activityLabel()} {$verb} : impression de l'extrait.")->with('print_extrait', route('actes-naissance.extrait', $extrait))
            : $redirect->with('status', "Acte {$acte->activityLabel()} {$verb}.");
    }
}
