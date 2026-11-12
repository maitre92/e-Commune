<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\DemandeVgtStatus;
use App\Enums\RecetteType;
use App\Enums\VgtCardTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ConfirmPaiementRequest;
use App\Http\Requests\Web\ConfirmRetraitRequest;
use App\Http\Requests\Web\StoreDemandeVgtRequest;
use App\Models\DemandeVgt;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\TarifVgt;
use App\Models\VignetteYear;
use App\Services\Caisse\CaisseService;
use App\Services\Vgt\DemandeVgtPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Écran Vignettes : en tête, le guichet (numéro de châssis → carte imprimée, GuichetVignetteController) ; en
 * dessous, l'historique des vignettes, avec réimpression dans l'un des 5 modèles. Cloisonné par mairie.
 */
class DemandeVgtController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', DemandeVgt::class);

        $currentYear = (int) date('Y');
        $mairie = request()->user()->mairie;

        return view('demandes-vgt.index', [
            // Un agent ne voit que les vignettes qu'il a délivrées ; le maire, le secrétaire général, le caissier : toutes.
            'demandes' => DemandeVgt::with(['moto.proprietaire', 'mairie', 'agent'])->visibleFor(request()->user())->orderByDesc('created_at')->get(),
            // Guichet : catégories de cylindrée actives de la mairie, et sa règle de retard.
            'guichetTarifs' => $mairie ? TarifVgt::gridFor($mairie->id, activeOnly: true) : collect(),
            'vgtMairie' => $mairie,
            // Réglages de la carte (bouton « Modèle de la vignette ») : le maire pour sa mairie (demandes-vgt.card_settings), l'administration pour le logo de toutes.
            // Logo et monument : toutes les mairies pour l'administration, la sienne pour le maire.
            'mairies' => request()->user()->can('mairies.update') ? Mairie::active()->orderBy('name')->get() : collect($mairie ? [$mairie] : []),
            'yearModels' => collect([$currentYear, $currentYear + 1])->mapWithKeys(fn (int $year) => [$year => [
                'template' => VignetteYear::templateFor($year, $mairie?->id),
                'locked' => $mairie ? VignetteYear::isLocked($year, $mairie->id) : true,
            ]]),
            'tarifs' => $mairie && Gate::allows('manageTarifs', DemandeVgt::class) ? TarifVgt::gridFor($mairie->id) : collect(),
        ]);
    }

    /**
     * Carte de vignette imprimable (recto + verso), dans le modèle de son année — un seul modèle par année.
     * Réservée aux vignettes payées ou retirées, visibles de l'utilisateur (cloisonnement, comme index()).
     */
    public function card(Request $request, int $demandeId): View
    {
        Gate::authorize('viewAny', DemandeVgt::class);

        $demande = DemandeVgt::with(['moto.proprietaire', 'mairie'])->visibleFor($request->user())->findOrFail($demandeId);
        abort_unless(in_array($demande->status, [DemandeVgtStatus::Payee, DemandeVgtStatus::Retiree], true), 404);

        $face = in_array($request->query('face'), ['recto', 'verso'], true) ? $request->query('face') : 'both';

        return view('demandes-vgt.card', ['demande' => $demande, 'template' => $demande->cardTemplate(), 'face' => $face, 'preview' => $request->boolean('preview')]);
    }

    /**
     * Aperçu d'un modèle avec des données d'exemple (choix du modèle de l'année par l'administration). Rien n'est
     * enregistré.
     */
    public function modelPreview(Request $request, string $template): View
    {
        $model = VgtCardTemplate::tryFrom($template) ?? abort(404);

        $mairie = $request->user()->mairie ?? Mairie::query()->orderBy('name')->first() ?? new Mairie(['name' => 'Mairie de démonstration']);
        $moto = new Moto(['chassis_number' => 'LX125ABC9001', 'type_or_brand' => '51 à 125 cm³']);
        $demande = new DemandeVgt(['vgt_year' => (int) $request->query('annee', date('Y')), 'holder_name' => 'Nom du détenteur', 'base_amount' => 6000, 'surcharge_amount' => 0]);
        $demande->id = 0;
        $demande->setRelation('moto', $moto->setRelation('proprietaire', null));
        $demande->setRelation('mairie', $mairie);

        $face = in_array($request->query('face'), ['recto', 'verso'], true) ? $request->query('face') : 'both';

        return view('demandes-vgt.card', ['demande' => $demande, 'template' => $model, 'face' => $face, 'preview' => true]);
    }

    /** Fixe le modèle de carte d'une année, tant qu'aucune vignette de cette année n'a été délivrée. */
    public function updateYearModel(Request $request, int $year): RedirectResponse
    {
        $data = $request->validate(['card_template' => ['required', Rule::enum(VgtCardTemplate::class)]]);

        $mairieId = $request->user()->mairie_id;
        if ($mairieId === null) {
            return redirect()->route('demandes-vgt.index')->with('error', 'Le modèle de l\'année se choisit par la mairie (compte du maire).');
        }

        if (VignetteYear::isLocked($year, $mairieId)) {
            return redirect()->route('demandes-vgt.index')->with('error', "Des vignettes {$year} ont déjà été délivrées : le modèle de cette année ne peut plus changer.");
        }

        $row = VignetteYear::where('year', $year)->first();
        $row ? $row->update(['card_template' => $data['card_template']]) : VignetteYear::create(['mairie_id' => $mairieId, 'year' => $year, 'card_template' => $data['card_template']]);

        return redirect()->route('demandes-vgt.index')->with('status', "Modèle des vignettes {$year} : ".VgtCardTemplate::from($data['card_template'])->label().'.');
    }

    public function store(StoreDemandeVgtRequest $request): RedirectResponse
    {
        $moto = Moto::findOrFail($request->validated('moto_id'));

        // mairie_id : celle de l'agent (BelongsToMairie) ; un compte sans mairie est refusé par le trait.
        $demande = DemandeVgt::create($request->validated() + $this->pricing($moto, (int) $request->validated('vgt_year')) + ['user_id' => $request->user()->id]);

        return redirect()->route('demandes-vgt.index')->with('status', "Vignette « {$demande->activityLabel()} » enregistrée.");
    }

    public function update(StoreDemandeVgtRequest $request, DemandeVgt $demandeVgt): RedirectResponse
    {
        $moto = Moto::findOrFail($request->validated('moto_id'));

        $demandeVgt->update($request->validated() + $this->pricing($moto, (int) $request->validated('vgt_year')));

        return redirect()->route('demandes-vgt.index')->with('status', "Vignette « {$demandeVgt->activityLabel()} » modifiée.");
    }

    /** Paiement confirmé : la moto est couverte jusqu'à l'année de la vignette (jamais en arrière). */
    public function confirmPaiement(ConfirmPaiementRequest $request, DemandeVgt $demandeVgt): RedirectResponse
    {
        DB::transaction(function () use ($request, $demandeVgt) {
            $demandeVgt->update([
                'status' => DemandeVgtStatus::Payee,
                'payment_confirmed_at' => $request->validated('payment_confirmed_at'),
            ]);

            $moto = $demandeVgt->moto;
            if ($moto && ($moto->vgt_year === null || $moto->vgt_year < $demandeVgt->vgt_year)) {
                $moto->update(['vgt_year' => $demandeVgt->vgt_year]);
            }

            app(CaisseService::class)->encaisser(RecetteType::Vignette, $demandeVgt->totalAmount(), "Vignette {$demandeVgt->vgt_year} — N° Engin {$moto?->chassis_number}", $demandeVgt, $request->user(), $demandeVgt->mairie_id);
        });

        return redirect()->route('demandes-vgt.index')->with('status', "Paiement de « {$demandeVgt->activityLabel()} » confirmé.");
    }

    public function confirmRetrait(ConfirmRetraitRequest $request, DemandeVgt $demandeVgt): RedirectResponse
    {
        $demandeVgt->update([
            'status' => DemandeVgtStatus::Retiree,
            'retrait_date' => $request->validated('retrait_date'),
        ]);

        return redirect()->route('demandes-vgt.index')->with('status', "Remise de la carte « {$demandeVgt->activityLabel()} » confirmée.");
    }

    /**
     * @return array{base_amount: int, is_late: bool, surcharge_amount: int}
     */
    private function pricing(Moto $moto, int $vgtYear): array
    {
        return app(DemandeVgtPricing::class)->for($moto, $vgtYear);
    }
}
