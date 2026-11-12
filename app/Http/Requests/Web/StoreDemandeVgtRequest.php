<?php

namespace App\Http\Requests\Web;

use App\Models\DemandeVgt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement (et modification tant qu'elle n'est pas payée) d'une vignette : une moto du registre de la
 * mairie, une année. Une seule vignette par moto et par année.
 */
class StoreDemandeVgtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $demandeVgt = $this->route('demandeVgt');

        return $demandeVgt instanceof DemandeVgt
            ? ($this->user()?->can('update', $demandeVgt) ?? false)
            : ($this->user()?->can('create', DemandeVgt::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('demandeVgt');

        return [
            'moto_id' => [
                'required',
                Rule::exists('motos', 'id')->where('mairie_id', $this->user()?->mairie_id)->whereNull('deleted_at'),
            ],
            'vgt_year' => [
                'required', 'integer', 'min:2000', 'max:'.((int) date('Y') + 1),
                Rule::unique('demandes_vgt', 'vgt_year')->where('moto_id', $this->input('moto_id'))->whereNull('deleted_at')
                    ->ignore($current instanceof DemandeVgt ? $current->getKey() : null),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'moto_id.required' => 'La moto est obligatoire.',
            'moto_id.exists' => 'Cette moto n\'existe pas dans le registre de votre mairie.',
            'vgt_year.unique' => 'Cette moto a déjà une vignette pour cette année.',
            'vgt_year.min' => 'Année de vignette invalide.',
            'vgt_year.max' => 'Année de vignette invalide.',
        ];
    }

    public function attributes(): array
    {
        return ['moto_id' => 'moto', 'vgt_year' => 'année de la vignette'];
    }
}
