<?php

namespace App\Services\EtatCivil;

use App\Enums\RecetteType;
use App\Http\Requests\Web\StoreActeNaissanceRequest;
use App\Models\ActeNaissance;
use App\Models\ExtraitNaissance;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Caisse\CaisseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Actes de naissance, partagé par le Web et l'application mobile : enregistre (ou corrige) l'acte rempli sur le
 * modèle d'extrait et, si l'agent a demandé l'impression, délivre l'extrait (conservé et journalisé).
 */
class ActeNaissanceService
{
    public function __construct(private readonly ActivityLogger $logger, private readonly CaisseService $caisse) {}

    /**
     * @return array{0: ActeNaissance, 1: ExtraitNaissance|null}
     */
    public function save(StoreActeNaissanceRequest $request, ?ActeNaissance $acte = null): array
    {
        return DB::transaction(function () use ($request, $acte) {
            if ($acte) {
                $acte->update($request->acteData());
            } else {
                $acte = ActeNaissance::create($request->acteData() + ['user_id' => $request->user()->id]);
            }

            if (! $request->boolean('print')) {
                return [$acte, null];
            }

            $extrait = ExtraitNaissance::withoutActivityLog(fn () => ExtraitNaissance::create($request->extraitData() + [
                'acte_naissance_id' => $acte->getKey(),
                'mairie_id' => $acte->mairie_id,
                'user_id' => $request->user()->getKey(),
            ]));

            $this->logger->log('actes-naissance.extract_delivered', 'actes-naissance', "Extrait délivré — acte {$acte->activityLabel()}", $acte, new: $request->extraitData());
            $this->encaisserExtrait($acte, $extrait, $request->user());

            return [$acte, $extrait];
        });
    }

    /**
     * Réimpression en un clic : un nouvel extrait, certifié par l'officier signataire réglé pour la mairie et daté
     * du jour — rien à ressaisir.
     *
     * @throws ValidationException réglages d'état civil incomplets
     */
    public function deliver(ActeNaissance $acte, User $user): ExtraitNaissance
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

        return DB::transaction(function () use ($acte, $user, $certification) {
            $extrait = ExtraitNaissance::withoutActivityLog(fn () => ExtraitNaissance::create($certification + [
                'acte_naissance_id' => $acte->getKey(),
                'mairie_id' => $acte->mairie_id,
                'user_id' => $user->getKey(),
            ]));
            $this->logger->log('actes-naissance.extract_delivered', 'actes-naissance', "Extrait délivré — acte {$acte->activityLabel()}", $acte, new: $certification);
            $this->encaisserExtrait($acte, $extrait, $user);

            return $extrait;
        });
    }

    /**
     * Aperçu de l'extrait tel qu'il serait délivré aujourd'hui (officier réglé, date du jour). Rien n'est enregistré
     * ni encaissé.
     */
    public function previewExtrait(ActeNaissance $acte): ExtraitNaissance
    {
        $mairie = $acte->mairie;
        $extrait = new ExtraitNaissance([
            'officer_name' => $mairie?->civil_officer_name ?? $acte->officer_name,
            'officer_title' => $mairie?->civil_officer_title ?? $acte->officer_title,
            'centre_type' => $mairie?->civil_centre_type ?? $acte->centre_type?->value,
            'centre_name' => $mairie?->civil_centre_name ?? $acte->centre_name,
            'delivered_at' => now()->toDateString(),
        ]);

        return $extrait->setRelation('acte', $acte);
    }

    /** Extrait payant (prix réglé par le maire) : la recette entre dans la caisse de l'agent qui l'a délivré. */
    private function encaisserExtrait(ActeNaissance $acte, ExtraitNaissance $extrait, User $user): void
    {
        $this->caisse->encaisser(
            RecetteType::Extrait,
            (int) ($acte->mairie?->civil_extract_price ?? 0),
            "Extrait — acte n° {$acte->act_number}/{$acte->act_year}, {$acte->fullName()}",
            $extrait,
            $user,
            $acte->mairie_id,
        );
    }

    /**
     * Valeurs proposées pour un nouvel acte : l'en-tête et l'officier du dernier acte de la mairie, le bloc de
     * certification du dernier extrait délivré.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $lastActe = ActeNaissance::latest('id')->first();
        $lastExtrait = ExtraitNaissance::latest('id')->first();

        return [
            ...($lastActe?->only([...ActeNaissance::HEADER_FIELDS, 'officer_name', 'officer_title']) ?? []),
            'cert_officer_name' => $lastExtrait?->officer_name,
            'cert_officer_title' => $lastExtrait?->officer_title,
            'cert_centre_type' => $lastExtrait?->centre_type ?? $lastActe?->centre_type,
            'cert_centre_name' => $lastExtrait?->centre_name ?? $lastActe?->centre_name,
        ];
    }

    /** Recherche par n° d'acte, nom, prénom ou NINA (30 résultats au plus). */
    public function search(string $term)
    {
        return ActeNaissance::query()
            ->where(fn ($query) => $query->where('act_number', $term)
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('first_names', 'like', "%{$term}%")
                ->orWhere('nina', $term))
            ->orderByDesc('act_year')->orderByDesc('id')->limit(30)->get();
    }
}
