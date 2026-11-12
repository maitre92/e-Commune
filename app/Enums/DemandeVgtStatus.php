<?php

namespace App\Enums;

/**
 * Statut d'une vignette délivrée par la mairie : enregistrée en attente du paiement → payée (la mairie confirme
 * la réception du paiement, l'année de la moto avance) → retirée (la carte physique a été remise au propriétaire).
 */
enum DemandeVgtStatus: string
{
    case EnAttente = 'en_attente';
    case Payee = 'payee';
    case Retiree = 'retiree';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'À payer',
            self::Payee => 'Payée',
            self::Retiree => 'Retirée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::EnAttente => 'bg-warning-subtle text-dark',
            self::Payee => 'bg-primary-subtle text-primary',
            self::Retiree => 'bg-secondary-subtle text-secondary',
        };
    }
}
