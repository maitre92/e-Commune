<?php

namespace App\Models\Concerns;

use App\Exceptions\MissingInstitutionException;
use App\Models\Mairie;
use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * À appliquer aux modèles métier rattachés à une mairie (colonne `mairie_id`) : cloisonnement fail-closed par
 * InstitutionScope. Le contournement `acrossMairies()` doit rester explicite et repérable (ex. la vérification
 * publique d'une vignette par QR Code).
 *
 * @mixin Model
 */
trait BelongsToMairie
{
    public static function bootBelongsToMairie(): void
    {
        static::addGlobalScope('mairie', new InstitutionScope('mairie_id'));

        static::creating(function (Model $model) {
            if ($model->getAttribute('mairie_id') === null && ($id = Auth::user()?->mairie_id)) {
                $model->setAttribute('mairie_id', $id);
            }

            // Logique métier, jamais contournée (même par le superadmin, D16) : contrairement à la permission,
            // ça ne passe pas par Gate::before. Sans ce garde-fou, un compte sans mairie (superadmin, admin
            // national) qui atteint quand même l'action plante sur la contrainte NOT NULL, sans message clair.
            if ($model->getAttribute('mairie_id') === null) {
                throw new MissingInstitutionException(class_basename($model));
            }
        });
    }

    public function mairie(): BelongsTo
    {
        return $this->belongsTo(Mairie::class)->withTrashed();
    }

    public function scopeAcrossMairies(Builder $query): Builder
    {
        return $query->withoutGlobalScope('mairie');
    }
}
