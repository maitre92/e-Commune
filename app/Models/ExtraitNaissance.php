<?php

namespace App\Models;

use App\Enums\CentreEtatCivilType;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extrait d'acte de naissance délivré (une ligne par délivrance) : qui l'a certifié, dans quel centre, quand.
 * Il se réimprime à l'identique ; il ne se modifie ni ne se supprime (traçabilité).
 */
#[Fillable(['mairie_id', 'acte_naissance_id', 'user_id', 'officer_name', 'officer_title', 'centre_type', 'centre_name', 'delivered_at'])]
class ExtraitNaissance extends Model
{
    use BelongsToMairie, LogsActivity;

    protected $table = 'extraits_naissance';

    public function activityModule(): string
    {
        return 'actes-naissance';
    }

    protected function casts(): array
    {
        return [
            'centre_type' => CentreEtatCivilType::class,
            'delivered_at' => 'date',
        ];
    }

    /** @return BelongsTo<ActeNaissance, $this> */
    public function acte(): BelongsTo
    {
        return $this->belongsTo(ActeNaissance::class, 'acte_naissance_id')->withoutGlobalScope('mairie')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function centreLabel(): string
    {
        return "Centre {$this->centre_type?->label()} de {$this->centre_name}";
    }

    public function activityNoun(): string
    {
        return 'Extrait d\'acte de naissance';
    }

    public function activityLabel(): string
    {
        return "n° {$this->acte?->act_number}/{$this->acte?->act_year} — {$this->acte?->fullName()}";
    }
}
