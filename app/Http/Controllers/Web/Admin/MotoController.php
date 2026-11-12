<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreMotoRequest;
use App\Http\Requests\Web\UpdateMotoRequest;
use App\Models\Moto;
use App\Models\Proprietaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Écran Motos (registre de la mairie) : chaque moto est liée à son propriétaire avant la délivrance d'une
 * vignette. Cloisonné par mairie (BelongsToMairie). Le matricule est l'identifiant national unique de la moto.
 */
class MotoController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Moto::class);

        return view('motos.index', [
            'motos' => Moto::with(['proprietaire', 'demandesVgt' => fn ($query) => $query->latest('id')])->orderBy('chassis_number')->get(),
            // Le select Propriétaire (modale) charge par recherche (Select2 AJAX, search()) plutôt que tout
            // précharger : ce booléen suffit à savoir s'il faut proposer « Ajouter » ou « Créez d'abord ... ».
            'hasProprietaires' => Proprietaire::query()->exists(),
        ]);
    }

    /**
     * Recherche pour le select Moto de la modale Vignette : registre trop peuplé pour tout charger. Format attendu par Select2 : `{"results": [{"id", "text"}, ...]}`.
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Moto::class);

        $term = trim((string) $request->query('q', ''));

        $motos = Moto::with('proprietaire')
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('chassis_number', 'like', "%{$term}%")
                ->orWhere('plate_number', 'like', "%{$term}%")
                ->orWhereHas('proprietaire', fn ($owner) => $owner->where('last_name', 'like', "%{$term}%")->orWhere('first_name', 'like', "%{$term}%"))
            ))
            ->orderBy('chassis_number')
            ->limit(20)
            ->get()
            ->map(fn (Moto $moto) => ['id' => $moto->id, 'text' => $moto->identifier().($moto->proprietaire ? ' — '.$moto->proprietaire->fullName() : '')]);

        return response()->json(['results' => $motos]);
    }

    public function store(StoreMotoRequest $request): RedirectResponse
    {
        $moto = Moto::create($request->validated());

        return redirect()->route('motos.index')->with('status', "Moto « {$moto->identifier()} » créée.");
    }

    public function update(UpdateMotoRequest $request, Moto $moto): RedirectResponse
    {
        $moto->update($request->validated());

        return redirect()->route('motos.index')->with('status', "Moto « {$moto->identifier()} » modifiée.");
    }

    public function destroy(Moto $moto): RedirectResponse
    {
        Gate::authorize('delete', $moto);

        $moto->delete();

        return redirect()->route('motos.index')->with('status', "Moto « {$moto->identifier()} » supprimée.");
    }
}
