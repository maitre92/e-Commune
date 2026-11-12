<?php

namespace App\Http\Requests\Web;

use App\Enums\CentreEtatCivilType;
use App\Enums\OptionMatrimoniale;
use App\Enums\RegimeMatrimonial;
use App\Models\ActeMariage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Création et modification d'un acte de mariage (rubriques 1 à 24 de l'extrait), saisies directement sur le
 * modèle d'extrait. Ce qui n'est pas tapé est complété : en-tête et officier (réglages de la mairie), numéro
 * suivant, date du jour, déclaration = célébration, lieu du mariage, nom du père = nom de l'époux / de l'épouse.
 * Avec `print=1`, l'extrait est délivré dans la foulée (certifié par l'officier réglé).
 */
class StoreActeMariageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $acte = $this->route('acteMariage');
        $user = $this->user();
        $allowed = $acte instanceof ActeMariage ? $user?->can('update', $acte) : $user?->can('create', ActeMariage::class);

        return ($allowed ?? false) && (! $this->boolean('print') || $user->can('actes-mariage.deliver'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $acte = $this->route('acteMariage');
        $text = fn (int $max, bool $required = false) => [$required ? 'required' : 'nullable', 'string', 'max:'.$max];

        return [
            'region' => $text(100),
            'cercle' => $text(100),
            'arrondissement' => $text(100),
            'commune' => $text(100),
            'centre_type' => ['required', Rule::enum(CentreEtatCivilType::class)],
            'centre_name' => $text(100, true),
            'act_number' => [
                'required', 'string', 'max:20',
                Rule::unique('actes_mariage', 'act_number')
                    ->where('mairie_id', $this->user()?->mairie_id)
                    ->where('centre_type', $this->input('centre_type'))
                    ->where('centre_name', $this->input('centre_name'))
                    ->where('act_year', $this->input('act_year'))
                    ->ignore($acte instanceof ActeMariage ? $acte->getKey() : null),
            ],
            'act_year' => ['required', 'integer', 'min:1900', 'max:'.date('Y')],

            'husband_first_names' => $text(150, true),
            'husband_last_name' => $text(100, true),
            'husband_birth_date' => ['required', 'date', 'before:celebrated_at'],
            'husband_birth_place' => $text(150, true),
            'husband_profession' => $text(100),
            'husband_domicile' => $text(150),

            'wife_first_names' => $text(150, true),
            'wife_last_name' => $text(100, true),
            'wife_birth_date' => ['required', 'date', 'before:celebrated_at'],
            'wife_birth_place' => $text(150, true),
            'wife_profession' => $text(100),
            'wife_domicile' => $text(150),

            'husband_father_first_names' => $text(150),
            'husband_father_last_name' => $text(100),
            'husband_mother_first_names' => $text(150),
            'husband_mother_last_name' => $text(100),
            'wife_father_first_names' => $text(150),
            'wife_father_last_name' => $text(100),
            'wife_mother_first_names' => $text(150),
            'wife_mother_last_name' => $text(100),

            'declared_at' => ['required', 'date', 'before_or_equal:today'],
            'celebrated_at' => ['required', 'date', 'before_or_equal:today'],
            'marriage_place' => $text(150, true),
            'regime' => ['required', Rule::enum(RegimeMatrimonial::class)],
            'option' => ['required', Rule::enum(OptionMatrimoniale::class)],
            'officer_name' => $text(150, true),
            'officer_title' => $text(150, true),
            'drawn_up_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /** Champs de l'acte. */
    public function acteData(): array
    {
        return $this->validated();
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['celebrated_at', 'act_year'])) {
                    return;
                }
                if ((int) $this->input('act_year') < (int) date('Y', strtotime((string) $this->input('celebrated_at')))) {
                    $validator->errors()->add('act_year', 'L\'année du registre ne peut pas précéder l\'année du mariage.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'act_number.unique' => 'Ce numéro d\'acte existe déjà pour ce centre et cette année.',
            'husband_birth_date.before' => 'La naissance de l\'époux doit précéder la célébration du mariage.',
            'wife_birth_date.before' => 'La naissance de l\'épouse doit précéder la célébration du mariage.',
            'celebrated_at.before_or_equal' => 'La date de célébration ne peut pas être dans le futur.',
            'declared_at.before_or_equal' => 'La date de déclaration ne peut pas être dans le futur.',
            'drawn_up_at.before_or_equal' => 'La date d\'établissement ne peut pas être dans le futur.',
            'regime.required' => 'Choisissez le régime matrimonial.',
            'option.required' => 'Choisissez l\'option matrimoniale.',
        ];
    }

    public function attributes(): array
    {
        return [
            'centre_type' => 'type de centre', 'centre_name' => 'nom du centre', 'act_number' => 'numéro de l\'acte',
            'act_year' => 'année du registre',
            'husband_first_names' => 'prénom(s) de l\'époux', 'husband_last_name' => 'nom de l\'époux',
            'husband_birth_date' => 'date de naissance de l\'époux', 'husband_birth_place' => 'localité de naissance de l\'époux',
            'husband_profession' => 'profession de l\'époux', 'husband_domicile' => 'domicile de l\'époux',
            'wife_first_names' => 'prénom(s) de l\'épouse', 'wife_last_name' => 'nom de l\'épouse',
            'wife_birth_date' => 'date de naissance de l\'épouse', 'wife_birth_place' => 'localité de naissance de l\'épouse',
            'wife_profession' => 'profession de l\'épouse', 'wife_domicile' => 'domicile de l\'épouse',
            'declared_at' => 'date de déclaration', 'celebrated_at' => 'date de célébration', 'marriage_place' => 'localité du mariage',
            'regime' => 'régime matrimonial', 'option' => 'option matrimoniale',
            'officer_name' => 'officier de l\'état civil', 'officer_title' => 'qualité de l\'officier', 'drawn_up_at' => 'date d\'établissement de l\'acte',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (array_keys($this->rules()) as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value));
                $data[$field] = $value === '' ? null : $value;
            } elseif ($value !== null) {
                $data[$field] = $value;
            }
        }
        $data['print'] = $this->boolean('print') ? '1' : '0';

        $acte = $this->route('acteMariage');
        $mairie = $this->user()?->mairie;
        $fill = function (string $field, $value) use (&$data) {
            if (($data[$field] ?? null) === null && $value !== null && $value !== '') {
                $data[$field] = $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
            }
        };

        if ($acte instanceof ActeMariage) {
            foreach (array_keys($this->rules()) as $field) {
                $fill($field, $acte->getAttribute($field));
            }
        } elseif ($mairie) {
            foreach (['region', 'cercle', 'arrondissement', 'commune', 'centre_type', 'centre_name', 'officer_name', 'officer_title'] as $field) {
                $fill($field, $mairie->getAttribute('civil_'.$field));
            }
            $fill('marriage_place', $mairie->civil_birth_place ?: $mairie->civil_centre_name);
            $fill('celebrated_at', now()->toDateString());
            $fill('drawn_up_at', now()->toDateString());
            $fill('act_year', (int) date('Y', strtotime((string) $data['drawn_up_at'])));
            $fill('act_number', $this->nextNumber($data));
        }

        // La déclaration se fait d'ordinaire le jour de la célébration ; les pères portent le nom de leur enfant.
        $fill('declared_at', $data['celebrated_at'] ?? null);
        if (! empty($data['husband_father_first_names'])) {
            $fill('husband_father_last_name', $data['husband_last_name'] ?? null);
        }
        if (! empty($data['wife_father_first_names'])) {
            $fill('wife_father_last_name', $data['wife_last_name'] ?? null);
        }

        $this->merge($data);
    }

    /** Numéro suivant du registre des mariages (même mairie, même centre, même année) : 1 pour le premier. */
    private function nextNumber(array $data): ?string
    {
        if (empty($data['centre_name']) || empty($data['act_year'])) {
            return null;
        }

        $max = ActeMariage::query()->withTrashed()
            ->where('centre_type', $data['centre_type'] ?? null)
            ->where('centre_name', $data['centre_name'])
            ->where('act_year', $data['act_year'])
            ->selectRaw('max(cast(act_number as unsigned)) as last')->value('last');

        return (string) (((int) $max) + 1);
    }
}
