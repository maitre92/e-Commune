<?php

namespace App\Policies;

use App\Enums\DemandeVgtStatus;
use App\Models\DemandeVgt;
use App\Models\User;

/**
 * Le cloisonnement (une mairie ne voit que ses vignettes) est déjà assuré en amont par BelongsToMairie ; la
 * Policy ajoute les règles propres à chaque étape. Les actions d'écriture sont réservées à un agent de la mairie
 * de la vignette (l'admin national supervise sans agir).
 */
class DemandeVgtPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('demandes-vgt.view');
    }

    public function view(User $user, DemandeVgt $demandeVgt): bool
    {
        return $user->can('demandes-vgt.view');
    }

    public function create(User $user): bool
    {
        return $user->can('demandes-vgt.create');
    }

    /** Une vignette se corrige tant que le paiement n'est pas confirmé. */
    public function update(User $user, DemandeVgt $demandeVgt): bool
    {
        return $user->can('demandes-vgt.update')
            && $this->ownMairie($user, $demandeVgt)
            && $demandeVgt->status === DemandeVgtStatus::EnAttente;
    }

    /** Corriger une vignette délivrée (CH mal recopié, nom du détenteur), puis la réimprimer. */
    public function correct(User $user, DemandeVgt $demandeVgt): bool
    {
        return $user->can('demandes-vgt.update')
            && $this->ownMairie($user, $demandeVgt)
            && in_array($demandeVgt->status, [DemandeVgtStatus::Payee, DemandeVgtStatus::Retiree], true)
            // Un agent corrige ses propres vignettes ; le maire (view_all) toutes celles de sa mairie.
            && ($user->can('demandes-vgt.view_all') || $demandeVgt->user_id === $user->id);
    }

    /** Tarifs de la mairie : il faut une mairie (les tarifs lui sont propres). */
    public function manageTarifs(User $user): bool
    {
        return $user->mairie_id !== null && $user->can('demandes-vgt.manage_tarifs');
    }

    /** La mairie confirme la réception du paiement d'une vignette en attente. */
    public function confirmPayment(User $user, DemandeVgt $demandeVgt): bool
    {
        return $user->can('demandes-vgt.confirm_payment')
            && $this->ownMairie($user, $demandeVgt)
            && $demandeVgt->status === DemandeVgtStatus::EnAttente;
    }

    /** La mairie confirme la remise de la carte physique d'une vignette payée. */
    public function confirmRetrait(User $user, DemandeVgt $demandeVgt): bool
    {
        return $user->can('demandes-vgt.confirm_retrait')
            && $this->ownMairie($user, $demandeVgt)
            && $demandeVgt->status === DemandeVgtStatus::Payee;
    }

    private function ownMairie(User $user, DemandeVgt $demandeVgt): bool
    {
        return $user->mairie_id !== null && $demandeVgt->mairie_id === $user->mairie_id;
    }
}
