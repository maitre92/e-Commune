<?php

namespace App\Services\Caisse;

use App\Enums\RecetteType;
use App\Models\Recette;
use App\Models\User;
use App\Models\Versement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Caisse de la mairie. L'agent qui délivre encaisse lui-même : chaque vignette vendue et chaque extrait payant
 * devient une recette numérotée (quittance) à son nom. En fin de journée, le caissier (ou le maire) reçoit
 * l'argent de l'agent et enregistre le versement.
 */
class CaisseService
{
    /**
     * Enregistre une recette (dans la transaction de la délivrance). Montant nul : rien n'est enregistré.
     */
    public function encaisser(RecetteType $type, int $amount, string $label, Model $subject, User $agent, int $mairieId): ?Recette
    {
        if ($amount <= 0) {
            return null;
        }

        $year = (int) date('Y');

        return DB::transaction(function () use ($type, $amount, $label, $subject, $agent, $mairieId, $year) {
            // Numéro de quittance suivant de la mairie pour l'année (verrou pour deux guichets simultanés).
            $last = Recette::query()->acrossMairies()->where('mairie_id', $mairieId)->where('annee', $year)->lockForUpdate()->max('numero');

            return Recette::create([
                'mairie_id' => $mairieId,
                'annee' => $year,
                'numero' => ((int) $last) + 1,
                'type' => $type,
                'amount' => $amount,
                'label' => mb_substr($label, 0, 255),
                'user_id' => $agent->getKey(),
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'encaissee_at' => now(),
            ]);
        });
    }

    /**
     * Le caissier (ou le maire) reçoit l'argent d'un agent : toutes ses recettes non versées passent dans un
     * versement. Rien à verser : erreur.
     *
     * @throws ValidationException
     */
    public function recevoir(User $agent, User $receiver): Versement
    {
        return DB::transaction(function () use ($agent, $receiver) {
            $recettes = Recette::query()->where('user_id', $agent->getKey())->nonVersees()->lockForUpdate()->get();

            if ($recettes->isEmpty()) {
                throw ValidationException::withMessages(['agent' => "{$agent->name} n'a rien à verser."]);
            }

            $versement = Versement::create([
                'mairie_id' => $recettes->first()->mairie_id,
                'agent_id' => $agent->getKey(),
                'received_by' => $receiver->getKey(),
                'amount' => $recettes->sum('amount'),
                'recettes_count' => $recettes->count(),
                'received_at' => now(),
            ]);

            Recette::withoutActivityLog(fn () => Recette::query()->whereKey($recettes->modelKeys())->get()->each->update(['versement_id' => $versement->id]));

            return $versement;
        });
    }

    /**
     * État de la caisse pour un jour, partagé par le Web et l'application mobile. Toute la caisse de la mairie si
     * l'utilisateur a `caisse.view` (maire, secrétaire général, caissier) ; sinon seulement ses propres recettes.
     *
     * @return array{full: bool, recettes: Collection<int, Recette>, a_verser: Collection<int, array{agent: ?User, nombre: int, total: int}>, types: Collection<int, RecetteType>}
     */
    public function etat(User $user, Carbon $day): array
    {
        $full = $user->can('caisse.view');

        $recettes = Recette::with('agent')
            ->when(! $full, fn ($query) => $query->where('user_id', $user->id))
            ->whereBetween('encaissee_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->orderByDesc('id')->get();

        // Ce que chaque agent a encore entre les mains (toutes dates confondues), à verser.
        $aVerser = Recette::query()->nonVersees()
            ->when(! $full, fn ($query) => $query->where('user_id', $user->id))
            ->selectRaw('user_id, count(*) as nombre, sum(amount) as total')->groupBy('user_id')->get()
            ->map(fn ($row) => ['agent' => User::withTrashed()->find($row->user_id), 'nombre' => (int) $row->nombre, 'total' => (int) $row->total]);

        // Types affichés : tous pour qui voit toute la caisse ; sinon ceux que l'agent délivre (un vendeur de
        // vignettes ne voit pas de ligne « extraits »).
        $types = collect(RecetteType::cases())->filter(fn (RecetteType $type) => $full || match ($type) {
            RecetteType::Vignette => $user->can('demandes-vgt.create'),
            RecetteType::Extrait => $user->can('actes-naissance.deliver'),
            RecetteType::ExtraitMariage => $user->can('actes-mariage.deliver'),
        })->values();

        return ['full' => $full, 'recettes' => $recettes, 'a_verser' => $aVerser, 'types' => $types];
    }
}
