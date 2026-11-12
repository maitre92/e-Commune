<?php

namespace App\Enums;

/**
 * Type du centre d'état civil où un acte est dressé ou un extrait délivré (« Centre principal / secondaire de … »).
 */
enum CentreEtatCivilType: string
{
    case Principal = 'principal';
    case Secondaire = 'secondaire';

    public function label(): string
    {
        return match ($this) {
            self::Principal => 'Principal',
            self::Secondaire => 'Secondaire',
        };
    }
}
