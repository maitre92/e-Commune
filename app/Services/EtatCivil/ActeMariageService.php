<?php

namespace App\Services\EtatCivil;

use App\Enums\RecetteType;
use App\Http\Requests\Web\StoreActeMariageRequest;
use App\Models\ActeMariage;
use App\Models\ExtraitMariage;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Caisse\CaisseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actes de mariage, partagé par le Web et l'application mobile : enregistre (ou corrige) l'acte rempli sur le
 * modèle d'extrait et, si demandé, délivre l'extrait certifié par l'officier réglé (conservé, journalisé, encaissé).
 */
class ActeMariageService
{
    public function __construct(private readonly ActivityLogger $logger, private readonly CaisseService $caisse) {}

    /**
     * @return array{0: ActeMariage, 1: ExtraitMariage|null}
     *
     * @throws ValidationException réglages d'état civil incomplets (à l'impression)
     */
    public function save(StoreActeMariageRequest $request, ?ActeMariage $acte = null): array
    {
        return DB::transaction(function () use ($request, $acte) {
            if ($acte) {
                $acte->update($request->acteData());
            } else {
                $acte = ActeMariage::create($request->acteData() + ['user_id' => $request->user()->id]);
            }

            return [$acte, $request->boolean('print') ? $this->deliver($acte, $request->user()) : null];
        });
    }

    /**
     * Délivrance en un clic : un nouvel extrait, certifié par l'officier signataire réglé et daté du jour.
     *
     * @throws ValidationException réglages d'état civil incomplets
     */
    public function deliver(ActeMariage $acte, User $user): ExtraitMariage
    {
        $mairie = $acte->mairie;
        if (! $mairie?->civilReady()) {
            throw ValidationException::withMessages(['reglages' => 'Renseignez d\'abord l\'officier signataire et le centre dans les réglages de l\'état civil.']);
        }

        $certification = [
            'officer_name' => $mairie->civil_officer_name,
            'officer_title' => $mairie->civil_officer_title,
            'centre_type' => $mairie->civil_centre_type ?? $acte->centre_type?->value,
            'centre_name' => $mairie->civil_centre_name,
            'delivered_at' => now()->toDateString(),
        ];

        return DB::transaction(function () use ($acte, $user, $certification, $mairie) {
            $extrait = ExtraitMariage::withoutActivityLog(fn () => ExtraitMariage::create($certification + [
                'acte_mariage_id' => $acte->getKey(),
                'mairie_id' => $acte->mairie_id,
                'user_id' => $user->getKey(),
            ]));
            $this->logger->log('actes-mariage.extract_delivered', 'actes-mariage', "Extrait délivré — acte de mariage {$acte->activityLabel()}", $acte, new: $certification);

            $this->caisse->encaisser(
                RecetteType::ExtraitMariage,
                (int) ($mairie->civil_marriage_extract_price ?? 0),
                "Extrait de mariage — acte n° {$acte->act_number}/{$acte->act_year}, {$acte->couple()}",
                $extrait,
                $user,
                $acte->mairie_id,
            );

            return $extrait;
        });
    }

    /** Aperçu de l'extrait tel qu'il serait délivré aujourd'hui : rien n'est enregistré ni encaissé. */
    public function previewExtrait(ActeMariage $acte): ExtraitMariage
    {
        $mairie = $acte->mairie;

        return (new ExtraitMariage([
            'officer_name' => $mairie?->civil_officer_name ?? $acte->officer_name,
            'officer_title' => $mairie?->civil_officer_title ?? $acte->officer_title,
            'centre_type' => $mairie?->civil_centre_type ?? $acte->centre_type?->value,
            'centre_name' => $mairie?->civil_centre_name ?? $acte->centre_name,
            'delivered_at' => now()->toDateString(),
        ]))->setRelation('acte', $acte);
    }

    /** Recherche par n° d'acte, nom ou prénom de l'un des époux (30 résultats au plus). */
    public function search(string $term)
    {
        return ActeMariage::query()
            ->where(fn ($query) => $query->where('act_number', $term)
                ->orWhere('husband_last_name', 'like', "%{$term}%")
                ->orWhere('husband_first_names', 'like', "%{$term}%")
                ->orWhere('wife_last_name', 'like', "%{$term}%")
                ->orWhere('wife_first_names', 'like', "%{$term}%"))
            ->orderByDesc('act_year')->orderByDesc('id')->limit(30)->get();
    }
}
