<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RecetteType;
use App\Http\Controllers\Controller;
use App\Models\Recette;
use App\Models\Versement;
use App\Services\Caisse\CaisseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Caisse de la mairie. L'agent voit ses recettes et ce qu'il doit verser ; le caissier (ou le maire) voit toute la
 * caisse du jour, reçoit le versement de chaque agent et imprime l'état journalier. Cloisonné par mairie.
 */
class CaisseController extends Controller
{
    public function index(Request $request, CaisseService $caisse): View
    {
        $user = $request->user();
        $day = $this->day($request);
        ['full' => $full, 'recettes' => $recettes, 'a_verser' => $aVerser, 'types' => $types] = $caisse->etat($user, $day);

        return view('caisse.index', [
            'full' => $full,
            'day' => $day,
            'recettes' => $recettes,
            'totals' => $types->mapWithKeys(fn (RecetteType $type) => [$type->value => [
                'label' => $type->plural(),
                'count' => $recettes->where('type', $type)->count(),
                'amount' => $recettes->where('type', $type)->sum('amount'),
            ]]),
            'aVerser' => $aVerser,
            'versements' => Versement::with(['agent', 'receiver'])
                ->when(! $full, fn ($query) => $query->where('agent_id', $user->id))
                ->whereBetween('received_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->orderByDesc('id')->get(),
        ]);
    }

    /** Le caissier (ou le maire) reçoit l'argent d'un agent de sa mairie. */
    public function receive(Request $request, CaisseService $caisse): RedirectResponse
    {
        // L'agent est retrouvé dans les recettes de la mairie (cloisonnées) : jamais celui d'une autre mairie.
        $agent = Recette::query()->where('user_id', $request->integer('agent_id'))->first()?->agent;
        abort_if($agent === null, 404);

        $versement = $caisse->recevoir($agent, $request->user());

        return redirect()->route('caisse.index')->with('status', "Versement de {$agent->name} reçu : ".number_format($versement->amount, 0, ',', ' ')." FCFA ({$versement->recettes_count} recette(s)).");
    }

    /** État journalier imprimable (A4) : toutes les recettes du jour et les versements. */
    public function etat(Request $request): View
    {
        $day = $this->day($request);
        $recettes = Recette::with('agent')->whereBetween('encaissee_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->orderBy('id')->get();

        return view('caisse.etat', [
            'day' => $day,
            'recettes' => $recettes,
            'mairie' => $request->user()->mairie,
            'versements' => Versement::with(['agent', 'receiver'])->whereBetween('received_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->get(),
        ]);
    }

    private function day(Request $request): Carbon
    {
        try {
            return $request->filled('date') ? Carbon::parse((string) $request->query('date')) : now();
        } catch (\Throwable) {
            return now();
        }
    }
}
