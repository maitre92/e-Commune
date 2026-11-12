<?php

namespace App\Models;

use App\Enums\CentreEtatCivilType;
use App\Enums\Genre;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ActeNaissanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Acte de naissance du registre d'une mairie (BelongsToMairie). Ses champs suivent les rubriques numérotées de
 * l'extrait d'acte de naissance (1 à 16) ; chaque extrait délivré est conservé (ExtraitNaissance).
 */
#[Fillable([
    'mairie_id', 'user_id', 'region', 'cercle', 'arrondissement', 'commune', 'centre_type', 'centre_name',
    'act_number', 'act_year', 'nina',
    'first_names', 'last_name', 'birth_date', 'birth_time', 'birth_place', 'gender',
    'father_first_names', 'father_last_name', 'father_profession', 'father_domicile',
    'mother_first_names', 'mother_last_name', 'mother_profession', 'mother_domicile',
    'officer_name', 'officer_title', 'drawn_up_at',
])]
class ActeNaissance extends Model
{
    /** @use HasFactory<ActeNaissanceFactory> */
    use BelongsToMairie, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'actes_naissance';

    /** Champs d'en-tête repris par défaut d'un acte à l'autre (même centre d'état civil). */
    public const HEADER_FIELDS = ['region', 'cercle', 'arrondissement', 'commune', 'centre_type', 'centre_name'];

    public function activityModule(): string
    {
        return 'actes-naissance';
    }

    protected function casts(): array
    {
        return [
            'centre_type' => CentreEtatCivilType::class,
            'gender' => Genre::class,
            'act_year' => 'integer',
            'birth_date' => 'date',
            'drawn_up_at' => 'date',
        ];
    }

    /** @return HasMany<ExtraitNaissance, $this> */
    public function extraits(): HasMany
    {
        return $this->hasMany(ExtraitNaissance::class);
    }

    /** Liste « derniers actes » : tous pour le maire (`actes-naissance.view_all`), ses propres actes pour un agent. */
    public function scopeVisibleFor(Builder $query, User $user): Builder
    {
        return $user->can('actes-naissance.view_all') ? $query : $query->where('user_id', $user->id);
    }

    public function fullName(): string
    {
        return trim("{$this->first_names} {$this->last_name}");
    }

    public function fatherFullName(): ?string
    {
        $name = trim("{$this->father_first_names} {$this->father_last_name}");

        return $name === '' ? null : $name;
    }

    public function motherFullName(): string
    {
        return trim("{$this->mother_first_names} {$this->mother_last_name}");
    }

    /** « Centre secondaire de Sébénikoro ». */
    public function centreLabel(): string
    {
        return "Centre {$this->centre_type?->label()} de {$this->centre_name}";
    }

    public function activityNoun(): string
    {
        return 'Acte de naissance';
    }

    public function activityLabel(): string
    {
        return "n° {$this->act_number}/{$this->act_year} — {$this->fullName()}";
    }
}
