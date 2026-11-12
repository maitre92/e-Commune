<?php

namespace App\Services\Vgt;

use App\Enums\DemandeVgtStatus;
use App\Enums\RecetteType;
use App\Exceptions\VignetteAlreadyIssuedException;
use App\Http\Requests\Web\GuichetVignetteRequest;
use App\Models\DemandeVgt;
use App\Models\Moto;
use App\Models\VignetteYear;
use App\Services\Caisse\CaisseService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guichet vignette, partagé par le Web et l'application mobile, comme au guichet réel : l'agent recopie le CH de
 * la moto, le citoyen paie, il repart avec sa vignette. La vignette est enregistrée payée et remise le jour même ;
 * une moto inconnue est enregistrée avec son seul châssis. La carte prend le modèle de l'année (un seul par
 * année, figé à la première vignette).
 */
class GuichetVignette
{
    public function __construct(private readonly DemandeVgtPricing $pricing, private readonly CaisseService $caisse) {}

    /**
     * @throws VignetteAlreadyIssuedException
     */
    public function issue(GuichetVignetteRequest $request): DemandeVgt
    {
        $year = (int) $request->validated('vgt_year');
        $moto = $request->knownMoto();

        if ($moto && DemandeVgt::where('moto_id', $moto->id)->where('vgt_year', $year)->exists()) {
            throw new VignetteAlreadyIssuedException($year);
        }

        return DB::transaction(function () use ($request, $moto, $year) {
            $tarif = $request->tarif();
            $category = $tarif?->type_or_brand ?? $request->validated('type_or_brand');

            $moto ??= Moto::create([
                'chassis_number' => $request->validated('chassis_number'),
                'type_or_brand' => $category,
            ]);

            // La cylindrée choisie au guichet (vue sur les papiers) complète ou corrige celle de la moto.
            if ($category !== null && $moto->type_or_brand !== $category) {
                $moto->update(['type_or_brand' => $category]);
            }

            VignetteYear::freeze($year, $moto->mairie_id);

            $demande = DemandeVgt::create($this->pricing->for($moto, $year, $tarif) + [
                'user_id' => $request->user()->id,
                'moto_id' => $moto->id,
                'vgt_year' => $year,
                'holder_name' => $request->validated('holder_name'),
                'holder_phone' => $request->validated('holder_phone'),
                'status' => DemandeVgtStatus::Retiree,
                'payment_confirmed_at' => now()->toDateString(),
                'retrait_date' => now()->toDateString(),
            ]);

            if ($moto->vgt_year === null || $moto->vgt_year < $year) {
                $moto->update(['vgt_year' => $year]);
            }

            // L'agent encaisse : la recette (quittance) entre dans sa caisse.
            $this->caisse->encaisser(RecetteType::Vignette, $demande->totalAmount(), "Vignette {$year} — N° Engin {$moto->chassis_number}", $demande, $request->user(), $demande->mairie_id);

            return $demande;
        });
    }

    /**
     * Corrige une vignette délivrée (CH mal recopié, nom du détenteur) : le montant ne change pas, la carte se
     * réimprime. Le bon CH peut être une moto déjà connue de la mairie (la vignette y est rattachée), ou un CH
     * nouveau (la moto est corrigée, ou une moto est créée si l'ancienne a d'autres vignettes). Journalisé.
     *
     * @throws ValidationException
     */
    public function correct(DemandeVgt $demande, string $chassisNumber, ?string $holderName, ?string $holderPhone = null): DemandeVgt
    {
        $chassis = Moto::normalizeChassis($chassisNumber);
        if ($chassis === '') {
            throw ValidationException::withMessages(['chassis_number' => 'Saisissez le N° Engin de la moto.']);
        }

        $phone = $holderPhone !== null && trim($holderPhone) !== '' ? PhoneNumber::normalize($holderPhone) : null;
        if ($holderPhone !== null && trim($holderPhone) !== '' && $phone === null) {
            throw ValidationException::withMessages(['holder_phone' => 'Numéro de téléphone du détenteur invalide (ex. 70 00 12 34).']);
        }

        return DB::transaction(function () use ($demande, $chassis, $holderName, $phone) {
            $wrong = $demande->moto;

            if ($wrong?->chassis_number !== $chassis) {
                $target = Moto::where('chassis_number', $chassis)->first();

                if ($target === null && Moto::query()->acrossMairies()->where('chassis_number', $chassis)->exists()) {
                    throw ValidationException::withMessages(['chassis_number' => 'Ce N° Engin est enregistré dans une autre mairie.']);
                }
                if ($target && DemandeVgt::where('moto_id', $target->id)->where('vgt_year', $demande->vgt_year)->whereKeyNot($demande->id)->exists()) {
                    throw ValidationException::withMessages(['chassis_number' => "Cette moto a déjà sa vignette {$demande->vgt_year}."]);
                }

                $otherVignettes = $wrong ? DemandeVgt::where('moto_id', $wrong->id)->whereKeyNot($demande->id)->exists() : true;

                if ($target === null && ! $otherVignettes) {
                    // CH simplement mal recopié : on corrige la moto elle-même.
                    $wrong->update(['chassis_number' => $chassis]);
                } else {
                    $target ??= Moto::create(['chassis_number' => $chassis, 'type_or_brand' => $wrong?->type_or_brand]);
                    $demande->update(['moto_id' => $target->id]);
                    if ($target->vgt_year === null || $target->vgt_year < $demande->vgt_year) {
                        $target->update(['vgt_year' => $demande->vgt_year]);
                    }
                    // La moto créée par erreur n'a plus aucune vignette : on la retire.
                    if ($wrong && ! $otherVignettes && $wrong->proprietaire_id === null) {
                        $wrong->delete();
                    }
                }
            }

            $demande->update([
                'holder_name' => $holderName !== null && trim($holderName) !== '' ? trim($holderName) : null,
                'holder_phone' => $phone,
            ]);

            return $demande->refresh()->load('moto');
        });
    }
}
