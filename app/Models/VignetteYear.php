<?php

namespace App\Models;

use App\Enums\VgtCardTemplate;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle de carte d'une année pour une mairie : toutes les vignettes de cette mairie pour cette année ont le même
 * visuel. Choisi par le maire (`demandes-vgt.card_settings`) ; figé dès la première vignette de l'année délivrée
 * par la mairie. Les lectures passent toujours par la mairie explicite (carte vérifiée par QR Code sans compte).
 */
#[Fillable(['mairie_id', 'year', 'card_template'])]
class VignetteYear extends Model
{
    use BelongsToMairie, LogsActivity;

    protected function casts(): array
    {
        return ['year' => 'integer', 'card_template' => VgtCardTemplate::class];
    }

    public function activityModule(): string
    {
        return 'demandes-vgt';
    }

    /**
     * Modèle de l'année pour la mairie : celui réglé pour l'année, sinon celui de la dernière année réglée avant,
     * sinon « officiel ».
     */
    public static function templateFor(int $year, ?int $mairieId): VgtCardTemplate
    {
        if ($mairieId === null) {
            return VgtCardTemplate::Officiel;
        }

        return static::query()->acrossMairies()->where('mairie_id', $mairieId)->where('year', '<=', $year)
            ->orderByDesc('year')->first()?->card_template ?? VgtCardTemplate::Officiel;
    }

    /** Fige le modèle de l'année de la mairie (appelé à sa première vignette de l'année). */
    public static function freeze(int $year, int $mairieId): VgtCardTemplate
    {
        $existing = static::query()->acrossMairies()->where('mairie_id', $mairieId)->where('year', $year)->first();

        return ($existing ?? static::create(['mairie_id' => $mairieId, 'year' => $year, 'card_template' => static::templateFor($year, $mairieId)->value]))->card_template;
    }

    /** La mairie a déjà délivré une vignette de cette année : son modèle ne change plus. */
    public static function isLocked(int $year, int $mairieId): bool
    {
        return DemandeVgt::query()->acrossMairies()->where('mairie_id', $mairieId)->where('vgt_year', $year)->exists();
    }

    public function activityNoun(): string
    {
        return 'Modèle de vignette';
    }

    public function activityLabel(): string
    {
        return "{$this->year} : {$this->card_template?->label()}";
    }
}
