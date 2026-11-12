<?php

namespace App\Policies;

use App\Models\ActeNaissance;
use App\Models\User;

/**
 * Le cloisonnement (une mairie ne voit que son registre) est assuré en amont par BelongsToMairie. Les écritures
 * sont réservées à un agent de la mairie de l'acte : l'admin national consulte sans modifier.
 */
class ActeNaissancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('actes-naissance.view');
    }

    public function view(User $user, ActeNaissance $acte): bool
    {
        return $user->can('actes-naissance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('actes-naissance.create') && $user->mairie_id !== null;
    }

    public function update(User $user, ActeNaissance $acte): bool
    {
        return $user->can('actes-naissance.update') && $this->ownMairie($user, $acte);
    }

    public function delete(User $user, ActeNaissance $acte): bool
    {
        return $user->can('actes-naissance.delete') && $this->ownMairie($user, $acte);
    }

    public function deliver(User $user, ActeNaissance $acte): bool
    {
        return $user->can('actes-naissance.deliver') && $this->ownMairie($user, $acte);
    }

    private function ownMairie(User $user, ActeNaissance $acte): bool
    {
        return $user->mairie_id !== null && $acte->mairie_id === $user->mairie_id;
    }
}
