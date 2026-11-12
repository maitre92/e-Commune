<?php

namespace App\Models;

use App\Enums\VgtCardTemplate;
use App\Enums\VgtLateMode;
use App\Models\Concerns\LogsActivity;
use Database\Factories\MairieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Institution : se supprime en soft delete uniquement, pour ne jamais orpheliner l'historique d'audit.
 * Désactiver une institution bloque tous ses utilisateurs (ARCHITECTURE §11).
 */
#[Fillable([
    'name', 'code', 'is_active', 'card_template', 'logo_path', 'monument_path',
    'civil_region', 'civil_cercle', 'civil_arrondissement', 'civil_commune', 'civil_centre_type', 'civil_centre_name',
    'civil_officer_name', 'civil_officer_title', 'civil_birth_place',
    'civil_print_mode', 'civil_print_offset_x', 'civil_print_offset_y', 'civil_extract_price', 'civil_marriage_extract_price',
    'vgt_deadline', 'vgt_late_mode', 'vgt_late_amount',
])]
class Mairie extends Model
{
    /** @use HasFactory<MairieFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'card_template' => VgtCardTemplate::class,
            'civil_print_offset_x' => 'float',
            'civil_print_offset_y' => 'float',
            'civil_extract_price' => 'integer',
            'civil_marriage_extract_price' => 'integer',
            'vgt_late_mode' => VgtLateMode::class,
            'vgt_late_amount' => 'integer',
        ];
    }

    /** Logo de la commune pour la carte VGT ; null = image par défaut (dessin neutre, `demandes-vgt._card_parts.arms`). */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    /** Image du monument pour la carte VGT ; null = dessin par défaut (`demandes-vgt._card_parts.tower`). */
    public function monumentUrl(): ?string
    {
        return $this->monument_path ? asset('storage/'.$this->monument_path) : null;
    }

    /** Champs des réglages d'état civil (saisis une fois, repris sur chaque acte et chaque extrait). */
    public const CIVIL_FIELDS = [
        'civil_region', 'civil_cercle', 'civil_arrondissement', 'civil_commune', 'civil_centre_type', 'civil_centre_name',
        'civil_officer_name', 'civil_officer_title', 'civil_birth_place',
    ];

    /** Réglages suffisants pour délivrer un extrait sans rien ressaisir (centre et officier signataire). */
    public function civilReady(): bool
    {
        return filled($this->civil_centre_name) && filled($this->civil_officer_name) && filled($this->civil_officer_title);
    }

    /** Date limite de la vignette de l'année $year (date limite réglée, 31 mars par défaut). */
    public function vgtDeadline(int $year): Carbon
    {
        [$month, $day] = array_pad(array_map('intval', explode('-', (string) ($this->vgt_deadline ?: '03-31'))), 2, 31);
        $first = Carbon::create($year, max(1, min(12, $month)), 1);

        return $first->setDay(max(1, min($day, $first->daysInMonth)))->endOfDay();
    }

    /** « 31 mars » : date limite lisible. */
    public function vgtDeadlineLabel(): string
    {
        return $this->vgtDeadline((int) date('Y'))->locale('fr')->translatedFormat('j F');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function activityNoun(): string
    {
        return 'Mairie';
    }
}
