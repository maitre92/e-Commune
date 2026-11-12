<?php

namespace App\Services\Vgt;

use App\Enums\VgtLateMode;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\TarifVgt;
use Illuminate\Support\Carbon;

/**
 * Prix d'une vignette, réglé par la mairie : le tarif de la catégorie de la moto (cylindrée), puis la pénalité de
 * retard de la mairie — passé sa date limite (31 mars par défaut) ou pour une année déjà passée, le prix est
 * doublé, majoré d'un montant fixe, ou inchangé. Le montant et la catégorie sont figés sur la vignette.
 */
class DemandeVgtPricing
{
    /**
     * @return array{base_amount: int, is_late: bool, surcharge_amount: int, category_label: ?string}
     */
    public function for(Moto $moto, int $vgtYear, ?TarifVgt $tarif = null, ?Carbon $on = null): array
    {
        $mairie = Mairie::withTrashed()->findOrFail($moto->mairie_id);
        $tarif ??= self::tarifFor($mairie->id, $moto->type_or_brand);
        $base = $tarif?->amount ?? TarifVgt::DEFAULT_AMOUNT;
        $isLate = self::isLate($mairie, $vgtYear, $on);

        return [
            'base_amount' => $base,
            'is_late' => $isLate,
            'surcharge_amount' => $isLate ? self::surcharge($mairie, $base) : 0,
            'category_label' => $tarif?->type_or_brand ?? $moto->type_or_brand,
        ];
    }

    /** Vignette en retard : année déjà passée, ou année en cours après la date limite de la mairie. */
    public static function isLate(Mairie $mairie, int $vgtYear, ?Carbon $on = null): bool
    {
        $on ??= now();

        return $vgtYear < $on->year || ($vgtYear === $on->year && $on->greaterThan($mairie->vgtDeadline($vgtYear)));
    }

    public static function surcharge(Mairie $mairie, int $base): int
    {
        return match ($mairie->vgt_late_mode ?? VgtLateMode::Double) {
            VgtLateMode::Double => $base,
            VgtLateMode::Fixe => (int) $mairie->vgt_late_amount,
            VgtLateMode::Aucune => 0,
        };
    }

    /**
     * Tarif de la catégorie de la moto ; catégorie inconnue → la seule catégorie active s'il n'y en a qu'une, sinon
     * aucune (tarif par défaut).
     */
    public static function tarifFor(int $mairieId, ?string $category): ?TarifVgt
    {
        if ($tarif = TarifVgt::findForMairie($mairieId, $category)) {
            return $tarif;
        }

        $active = TarifVgt::gridFor($mairieId, activeOnly: true);

        return $active->count() === 1 ? $active->first() : null;
    }
}
