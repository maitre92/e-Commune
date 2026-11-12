<?php

namespace App\Enums;

/**
 * Genre d'une personne (propriétaire d'une moto, enfant d'un acte de naissance). Pas d'enum SQL (MariaDB 10.4) :
 * colonne string, ce type PHP.
 */
enum Genre: string
{
    case Homme = 'homme';
    case Femme = 'femme';

    public function label(): string
    {
        return match ($this) {
            self::Homme => 'Homme',
            self::Femme => 'Femme',
        };
    }

    /** Mention « Sexe » de l'extrait d'acte de naissance. */
    public function sexLabel(): string
    {
        return match ($this) {
            self::Homme => 'Masculin',
            self::Femme => 'Féminin',
        };
    }
}
