<?php

namespace App\Models;

use App\Enums\CentreEtatCivilType;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extrait d'acte de mariage délivré (une ligne par délivrance) : qui l'a certifié, dans quel centre, quand. Il se
 * réimprime à l'identique ; il ne se modifie ni ne se supprime (traçabilité).
 */
#[Fillable(['mairie_id', 'acte_mariage_id', 'user_id', 'officer_name', 'officer_title', 'centre_type', 'centre_name', 'delivered_at'])]
class ExtraitMariage extends Model
{
    use BelongsToMairie, LogsActivity;

    protected $table = 'extraits_mariage';

    public function activityModule(): string
    {
        return 'actes-mariage';
    }

    protected function casts(): array
    {
        return [
            'centre_type' => CentreEtatCivilType::class,
            'delivered_at' => 'date',
        ];
    }

    /** @return BelongsTo<ActeMariage, $this> */
    public function acte(): BelongsTo
    {
        return $this->belongsTo(ActeMariage::class, 'acte_mariage_id')->withoutGlobalScope('mairie')->withTrashed();
    }

    public function activityNoun(): string
    {
        return 'Extrait d\'acte de mariage';
    }

    public function activityLabel(): string
    {
        return "n° {$this->acte?->act_number}/{$this->acte?->act_year} — {$this->acte?->couple()}";
    }
}
