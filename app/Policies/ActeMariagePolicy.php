<?php

namespace App\Policies;

use App\Models\ActeMariage;
use App\Models\User;

/**
 * Comme les actes de naissance : le cloisonnement par mairie est assuré par BelongsToMairie ; les écritures sont
 * réservées à un agent de la mairie de l'acte (l'admin national consulte sans modifier).
 */
class ActeMariagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('actes-mariage.view');
    }

    public function view(User $user, ActeMariage $acte): bool
    {
        return $user->can('actes-mariage.view');
    }

    public function create(User $user): bool
    {
        return $user->can('actes-mariage.create') && $user->mairie_id !== null;
    }

    public function update(User $user, ActeMariage $acte): bool
    {
        return $user->can('actes-mariage.update') && $this->ownMairie($user, $acte);
    }

    public function delete(User $user, ActeMariage $acte): bool
    {
        return $user->can('actes-mariage.delete') && $this->ownMairie($user, $acte);
    }

    public function deliver(User $user, ActeMariage $acte): bool
    {
        return $user->can('actes-mariage.deliver') && $this->ownMairie($user, $acte);
    }

    private function ownMairie(User $user, ActeMariage $acte): bool
    {
        return $user->mairie_id !== null && $acte->mairie_id === $user->mairie_id;
    }
}
