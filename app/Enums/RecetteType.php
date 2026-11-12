<?php

namespace App\Enums;

/** Nature d'une recette de la caisse de la mairie. */
enum RecetteType: string
{
    case Vignette = 'vignette';
    case Extrait = 'extrait';
    case ExtraitMariage = 'extrait_mariage';

    public function label(): string
    {
        return match ($this) {
            self::Vignette => 'Vignette',
            self::Extrait => 'Extrait d\'acte de naissance',
            self::ExtraitMariage => 'Extrait d\'acte de mariage',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Vignette => 'Vignettes',
            self::Extrait => 'Extraits d\'acte de naissance',
            self::ExtraitMariage => 'Extraits d\'acte de mariage',
        };
    }
}
