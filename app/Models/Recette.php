<?php

namespace App\Models;

use App\Enums\RecetteType;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recette de la caisse : un encaissement (vignette vendue, extrait payant), numéroté par mairie et par année
 * (quittance), au nom de l'agent qui a encaissé. Elle ne se modifie pas ; elle passe seulement dans un versement.
 */
#[Fillable(['mairie_id', 'annee', 'numero', 'type', 'amount', 'label', 'user_id', 'subject_type', 'subject_id', 'versement_id', 'encaissee_at'])]
class Recette extends Model
{
    use BelongsToMairie, LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => RecetteType::class,
            'amount' => 'integer',
            'annee' => 'integer',
            'numero' => 'integer',
            'encaissee_at' => 'datetime',
        ];
    }

    public function activityModule(): string
    {
        return 'caisse';
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return BelongsTo<Versement, $this> */
    public function versement(): BelongsTo
    {
        return $this->belongsTo(Versement::class)->withoutGlobalScope('mairie');
    }

    /** Recettes pas encore versées (encore entre les mains de l'agent). */
    public function scopeNonVersees(Builder $query): Builder
    {
        return $query->whereNull('versement_id');
    }

    /** « Q2026-000001 ». */
    public function quittance(): string
    {
        return sprintf('Q%d-%06d', $this->annee, $this->numero);
    }

    public function activityNoun(): string
    {
        return 'Recette';
    }

    public function activityLabel(): string
    {
        return "{$this->quittance()} — {$this->amount} FCFA";
    }
}
