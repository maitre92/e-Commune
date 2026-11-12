<?php

namespace App\Enums;

/**
 * Règle de retard d'une vignette, réglée par chaque mairie : passé la date limite de l'année (ou pour une année
 * déjà passée), le prix est doublé (pratique rapportée à Bamako), majoré d'un montant fixe, ou inchangé.
 */
enum VgtLateMode: string
{
    case Double = 'double';
    case Fixe = 'fixe';
    case Aucune = 'aucune';

    public function label(): string
    {
        return match ($this) {
            self::Double => 'Prix doublé',
            self::Fixe => 'Majoration fixe',
            self::Aucune => 'Aucune pénalité',
        };
    }
}
