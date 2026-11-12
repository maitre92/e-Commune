<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Versement d'un agent : l'argent de ses recettes remis au caissier (ou au maire), qui le reçoit. */
#[Fillable(['mairie_id', 'agent_id', 'received_by', 'amount', 'recettes_count', 'received_at'])]
class Versement extends Model
{
    use BelongsToMairie, LogsActivity;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'recettes_count' => 'integer', 'received_at' => 'datetime'];
    }

    public function activityModule(): string
    {
        return 'caisse';
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    /** @return HasMany<Recette, $this> */
    public function recettes(): HasMany
    {
        return $this->hasMany(Recette::class)->withoutGlobalScope('mairie');
    }

    public function activityNoun(): string
    {
        return 'Versement';
    }

    public function activityLabel(): string
    {
        return "{$this->agent?->name} — {$this->amount} FCFA";
    }
}
