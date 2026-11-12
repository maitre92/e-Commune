<?php

namespace App\Support;

use App\Models\Mairie;
use Throwable;

/**
 * La mairie de cette installation : celle fixée par MAIRIE_ID (.env), sinon la seule mairie active. Sert à
 * présenter la mairie sur la page de connexion (nom, armoiries). Plusieurs mairies sans MAIRIE_ID : aucune
 * (présentation générique). Jamais d'erreur si la base n'est pas prête.
 */
class HomeMairie
{
    public static function current(): ?Mairie
    {
        try {
            if ($id = config('brand.mairie_id')) {
                return Mairie::query()->whereKey($id)->first();
            }

            $active = Mairie::query()->active()->limit(2)->get();

            return $active->count() === 1 ? $active->first() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Armoiries à afficher : le logo téléversé par la mairie, sinon les armoiries par défaut. */
    public static function armsUrl(?Mairie $mairie): string
    {
        return $mairie?->logoUrl() ?? asset('assets/images/vignette/arms-bamako.png');
    }
}
