<?php

namespace App\Models;

use App\Enums\CentreEtatCivilType;
use App\Enums\OptionMatrimoniale;
use App\Enums\RegimeMatrimonial;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ActeMariageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Acte de mariage du registre d'une mairie (BelongsToMairie). Ses champs suivent les rubriques numérotées de
 * l'extrait d'acte de mariage malien (1 à 24) ; chaque extrait délivré est conservé (ExtraitMariage).
 */
#[Fillable([
    'mairie_id', 'user_id', 'region', 'cercle', 'arrondissement', 'commune', 'centre_type', 'centre_name', 'act_number', 'act_year',
    'husband_first_names', 'husband_last_name', 'husband_birth_date', 'husband_birth_place', 'husband_profession', 'husband_domicile',
    'wife_first_names', 'wife_last_name', 'wife_birth_date', 'wife_birth_place', 'wife_profession', 'wife_domicile',
    'husband_father_first_names', 'husband_father_last_name', 'husband_mother_first_names', 'husband_mother_last_name',
    'wife_father_first_names', 'wife_father_last_name', 'wife_mother_first_names', 'wife_mother_last_name',
    'declared_at', 'celebrated_at', 'marriage_place', 'regime', 'option', 'officer_name', 'officer_title', 'drawn_up_at',
])]
class ActeMariage extends Model
{
    /** @use HasFactory<ActeMariageFactory> */
    use BelongsToMairie, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'actes_mariage';

    public function activityModule(): string
    {
        return 'actes-mariage';
    }

    protected function casts(): array
    {
        return [
            'centre_type' => CentreEtatCivilType::class,
            'regime' => RegimeMatrimonial::class,
            'option' => OptionMatrimoniale::class,
            'act_year' => 'integer',
            'husband_birth_date' => 'date',
            'wife_birth_date' => 'date',
            'declared_at' => 'date',
            'celebrated_at' => 'date',
            'drawn_up_at' => 'date',
        ];
    }

    /** @return HasMany<ExtraitMariage, $this> */
    public function extraits(): HasMany
    {
        return $this->hasMany(ExtraitMariage::class);
    }

    /** Liste « derniers actes » : tous pour le maire (`actes-mariage.view_all`), ses propres actes pour un agent. */
    public function scopeVisibleFor(Builder $query, User $user): Builder
    {
        return $user->can('actes-mariage.view_all') ? $query : $query->where('user_id', $user->id);
    }

    public function husbandName(): string
    {
        return trim("{$this->husband_first_names} {$this->husband_last_name}");
    }

    public function wifeName(): string
    {
        return trim("{$this->wife_first_names} {$this->wife_last_name}");
    }

    /** « Moustapha BARRY et Adama DIALLO ». */
    public function couple(): string
    {
        return $this->husbandName().' et '.$this->wifeName();
    }

    public function centreLabel(): string
    {
        return "Centre {$this->centre_type?->label()} de {$this->centre_name}";
    }

    public function activityNoun(): string
    {
        return 'Acte de mariage';
    }

    public function activityLabel(): string
    {
        return "n° {$this->act_number}/{$this->act_year} — {$this->couple()}";
    }
}
