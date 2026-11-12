<?php

namespace App\Enums;

/** Rubrique 21 de l'extrait d'acte de mariage. */
enum OptionMatrimoniale: string
{
    case Monogamie = 'monogamie';
    case Polygamie = 'polygamie';

    public function label(): string
    {
        return match ($this) {
            self::Monogamie => 'Monogamie',
            self::Polygamie => 'Polygamie',
        };
    }
}
