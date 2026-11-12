<?php

namespace App\Http\Requests\Web;

use App\Enums\CentreEtatCivilType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Réglages d'état civil de la mairie de l'agent : en-tête des extraits, officier signataire, localité par défaut. */
class UpdateCivilSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->mairie_id !== null && ($user->can('actes-naissance.configure') || $user->can('actes-mariage.configure'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'civil_region' => ['nullable', 'string', 'max:100'],
            'civil_cercle' => ['nullable', 'string', 'max:100'],
            'civil_arrondissement' => ['nullable', 'string', 'max:100'],
            'civil_commune' => ['nullable', 'string', 'max:100'],
            'civil_centre_type' => ['required', Rule::enum(CentreEtatCivilType::class)],
            'civil_centre_name' => ['required', 'string', 'max:100'],
            'civil_officer_name' => ['required', 'string', 'max:150'],
            'civil_officer_title' => ['required', 'string', 'max:150'],
            'civil_birth_place' => ['nullable', 'string', 'max:150'],
            'civil_print_mode' => ['required', 'in:blanc,preimprime'],
            'civil_extract_price' => ['required', 'integer', 'min:0', 'max:100000'],
            'civil_marriage_extract_price' => ['required', 'integer', 'min:0', 'max:100000'],
            'civil_print_offset_x' => ['nullable', 'numeric', 'between:-30,30'],
            'civil_print_offset_y' => ['nullable', 'numeric', 'between:-30,30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'civil_print_mode' => $this->input('civil_print_mode', 'blanc'),
            'civil_extract_price' => (int) preg_replace('/\D/', '', (string) $this->input('civil_extract_price', $this->user()?->mairie?->civil_extract_price ?? 0)),
            'civil_marriage_extract_price' => (int) preg_replace('/\D/', '', (string) $this->input('civil_marriage_extract_price', $this->user()?->mairie?->civil_marriage_extract_price ?? 0)),
            'civil_print_offset_x' => str_replace(',', '.', (string) ($this->input('civil_print_offset_x') ?: 0)),
            'civil_print_offset_y' => str_replace(',', '.', (string) ($this->input('civil_print_offset_y') ?: 0)),
        ]);
    }

    public function attributes(): array
    {
        return [
            'civil_region' => 'région', 'civil_cercle' => 'cercle', 'civil_arrondissement' => 'arrondissement',
            'civil_commune' => 'commune', 'civil_centre_type' => 'type de centre', 'civil_centre_name' => 'nom du centre',
            'civil_officer_name' => 'officier de l\'état civil', 'civil_officer_title' => 'qualité de l\'officier',
            'civil_birth_place' => 'localité de naissance par défaut',
            'civil_print_mode' => 'mode d\'impression', 'civil_extract_price' => 'prix de l\'extrait de naissance', 'civil_marriage_extract_price' => 'prix de l\'extrait de mariage', 'civil_print_offset_x' => 'décalage horizontal',
            'civil_print_offset_y' => 'décalage vertical',
        ];
    }
}
