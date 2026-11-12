<?php

namespace App\Http\Requests\Web;

use App\Enums\VgtLateMode;
use App\Models\DemandeVgt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tarifs des vignettes de la mairie, en un seul formulaire : les catégories de cylindrée (tranche, montant,
 * active ou non), une nouvelle catégorie facultative, et la pénalité de retard (date limite, règle, montant).
 */
class SaveTarifsVgtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->mairie_id !== null && $this->user()->can('manageTarifs', DemandeVgt::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tarifs' => ['array'],
            'tarifs.*.cylinder_min' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'tarifs.*.cylinder_max' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'tarifs.*.amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'tarifs.*.is_active' => ['boolean'],
            'new_label' => ['nullable', 'string', 'max:100', Rule::unique('tarifs_vgt', 'type_or_brand')->where('mairie_id', $this->user()?->mairie_id)],
            'new_cylinder_min' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'new_cylinder_max' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'new_amount' => ['nullable', 'required_with:new_label', 'integer', 'min:0', 'max:10000000'],
            'deadline_day' => ['required', 'integer', 'between:1,31'],
            'deadline_month' => ['required', 'integer', 'between:1,12'],
            'vgt_late_mode' => ['required', Rule::enum(VgtLateMode::class)],
            'vgt_late_amount' => ['nullable', 'required_if:vgt_late_mode,fixe', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $rows = array_merge(
                array_values((array) $this->input('tarifs', [])),
                [['cylinder_min' => $this->input('new_cylinder_min'), 'cylinder_max' => $this->input('new_cylinder_max')]],
            );
            foreach ($rows as $row) {
                if (filled($row['cylinder_min'] ?? null) && filled($row['cylinder_max'] ?? null) && (int) $row['cylinder_min'] > (int) $row['cylinder_max']) {
                    $validator->errors()->add('tarifs', 'Une tranche de cylindrée commence après sa fin (« de » doit être inférieur à « à »).');

                    return;
                }
            }

            $active = collect((array) $this->input('tarifs', []))->filter(fn ($row) => (bool) ($row['is_active'] ?? false))->count();
            if ($active === 0 && ! filled($this->input('new_label'))) {
                $validator->errors()->add('tarifs', 'Gardez au moins une catégorie active : le guichet en a besoin.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'tarifs.*.amount.required' => 'Chaque catégorie doit avoir un montant.',
            'new_label.unique' => 'Cette catégorie existe déjà.',
            'new_amount.required_with' => 'Indiquez le montant de la nouvelle catégorie.',
            'vgt_late_amount.required_if' => 'Indiquez le montant de la majoration fixe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'new_label' => 'nouvelle catégorie', 'new_amount' => 'montant', 'deadline_day' => 'jour', 'deadline_month' => 'mois',
            'vgt_late_mode' => 'pénalité', 'vgt_late_amount' => 'majoration',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tarifs = collect((array) $this->input('tarifs', []))->map(fn ($row) => [
            'cylinder_min' => filled($row['cylinder_min'] ?? null) ? $row['cylinder_min'] : null,
            'cylinder_max' => filled($row['cylinder_max'] ?? null) ? $row['cylinder_max'] : null,
            'amount' => $row['amount'] ?? null,
            'is_active' => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ])->all();

        $this->merge([
            'tarifs' => $tarifs,
            'new_label' => filled($this->input('new_label')) ? trim((string) $this->input('new_label')) : null,
        ]);
    }
}
