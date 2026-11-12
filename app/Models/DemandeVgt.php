<?php

namespace App\Models;

use App\Enums\DemandeVgtStatus;
use App\Enums\VgtCardTemplate;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use App\Support\PhoneNumber;
use Database\Factories\DemandeVgtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vignette (VGT) d'une moto pour une année civile, enregistrée et délivrée par la mairie (BelongsToMairie) :
 * à payer → payée → carte retirée (App\Enums\DemandeVgtStatus). Le tarif (selon le genre de la moto) et la
 * majoration pour arriéré (config('vgt.late_surcharge_amount'), quand l'année demandée est déjà passée) sont
 * figés à l'enregistrement.
 */
#[Fillable([
    'mairie_id', 'user_id', 'moto_id', 'vgt_year', 'holder_name', 'holder_phone', 'category_label', 'status', 'base_amount', 'is_late', 'surcharge_amount',
    'payment_confirmed_at', 'retrait_date',
])]
class DemandeVgt extends Model
{
    /** @use HasFactory<DemandeVgtFactory> */
    use BelongsToMairie, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'demandes_vgt';

    /** Module (permissions, audit) « demandes-vgt » plutôt que le nom de la table, en soulignés. */
    public function activityModule(): string
    {
        return 'demandes-vgt';
    }

    protected function casts(): array
    {
        return [
            'status' => DemandeVgtStatus::class,
            'vgt_year' => 'integer',
            'is_late' => 'boolean',
            'base_amount' => 'integer',
            'surcharge_amount' => 'integer',
            'payment_confirmed_at' => 'date',
            'retrait_date' => 'date',
        ];
    }

    /**
     * La vignette est déjà cloisonnée : sa moto se charge sans refiltrer (même mairie par construction), y
     * compris sur la page publique de vérification.
     *
     * @return BelongsTo<Moto, $this>
     */
    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class)->withoutGlobalScope('mairie')->withTrashed();
    }

    public function totalAmount(): int
    {
        return $this->base_amount + $this->surcharge_amount;
    }

    /**
     * Ce que l'utilisateur voit : toutes les vignettes de la mairie pour le maire, le secrétaire général, le
     * caissier (`demandes-vgt.view_all`) ; seulement celles qu'il a délivrées pour un agent.
     */
    public function scopeVisibleFor(Builder $query, User $user): Builder
    {
        return $user->can('demandes-vgt.view_all') ? $query : $query->where('user_id', $user->id);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** Nom imprimé au verso : celui noté au guichet, sinon le propriétaire enregistré de la moto. */
    public function holderName(): string
    {
        return (string) ($this->holder_name ?: $this->moto?->proprietaire?->fullName());
    }

    public function holderPhone(): string
    {
        return PhoneNumber::display($this->holder_phone ?: $this->moto?->proprietaire?->phone);
    }

    /** Modèle de carte de l'année de la vignette (un seul par année). */
    public function cardTemplate(): VgtCardTemplate
    {
        return VignetteYear::templateFor($this->vgt_year, $this->mairie_id);
    }

    public function activityNoun(): string
    {
        return 'Vignette';
    }

    public function activityLabel(): string
    {
        return "{$this->moto?->identifier()} ({$this->vgt_year})";
    }
}
