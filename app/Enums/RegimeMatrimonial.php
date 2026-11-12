<?php

namespace App\Enums;

/** Rubrique 20 de l'extrait d'acte de mariage. */
enum RegimeMatrimonial: string
{
    case SeparationBiens = 'separation_biens';
    case CommunauteBiens = 'communaute_biens';

    public function label(): string
    {
        return match ($this) {
            self::SeparationBiens => 'Séparation des biens',
            self::CommunauteBiens => 'Communauté des biens',
        };
    }
}
