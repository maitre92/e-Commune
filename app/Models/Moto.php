<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Database\Factories\MotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Moto enregistrée par l'agent de la mairie, liée à son propriétaire. Cloisonnée par mairie (BelongsToMairie)
 * comme Proprietaire. Le numéro de châssis (châssis) est l'identifiant unique de la moto ; le matricule est facultatif.
 * `vgt_year` : dernière année couverte par une vignette ; avancée automatiquement au paiement d'une vignette.
 */
#[Fillable([
    'mairie_id', 'proprietaire_id', 'chassis_number', 'plate_number', 'color', 'type_or_brand', 'vgt_year',
    'has_sale_certificate', 'seller_first_name', 'seller_last_name', 'seller_phone', 'seller_address',
    'has_witness', 'witness_first_name', 'witness_last_name', 'witness_phone', 'witness_address',
])]
class Moto extends Model
{
    /** @use HasFactory<MotoFactory> */
    use BelongsToMairie, HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'vgt_year' => 'integer',
            'has_sale_certificate' => 'boolean',
            'has_witness' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Proprietaire, $this>
     */
    public function proprietaire(): BelongsTo
    {
        // La moto est déjà cloisonnée : son propriétaire (même mairie par construction) se charge sans refiltrer.
        return $this->belongsTo(Proprietaire::class)->withoutGlobalScope('mairie')->withTrashed();
    }

    /**
     * @return HasMany<DemandeVgt, $this>
     */
    public function demandesVgt(): HasMany
    {
        return $this->hasMany(DemandeVgt::class);
    }

    /** Vignette à jour : valable pour l'année civile en cours. */
    public function isVgtCurrent(): bool
    {
        return $this->vgt_year !== null && $this->vgt_year >= now()->year;
    }

    public function sellerFullName(): ?string
    {
        return $this->has_sale_certificate ? trim("{$this->seller_first_name} {$this->seller_last_name}") : null;
    }

    public function witnessFullName(): ?string
    {
        return $this->has_witness ? trim("{$this->witness_first_name} {$this->witness_last_name}") : null;
    }

    public function activityNoun(): string
    {
        return 'Moto';
    }

    public function activityLabel(): string
    {
        return $this->identifier();
    }

    /** Numéro imprimé sur la carte et affiché partout : le matricule s'il existe, sinon le numéro de châssis. */
    public function identifier(): string
    {
        return (string) ($this->plate_number ?: $this->chassis_number);
    }

    /** Normalise un numéro de châssis saisi au guichet (majuscules, sans espaces). */
    public static function normalizeChassis(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $value));
    }
}
