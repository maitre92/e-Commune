<?php

namespace App\Http\Requests\Web;

use App\Enums\CentreEtatCivilType;
use App\Enums\Genre;
use App\Models\ActeNaissance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Création et modification d'un acte de naissance (rubriques 1 à 16 de l'extrait), saisies directement sur le
 * modèle d'extrait. Avec `print=1`, le bloc de certification (`cert_*` : officier qui délivre, son centre, la
 * date) est aussi exigé et un extrait est délivré. Le numéro d'acte est unique par centre et par année au sein
 * de la mairie (actes supprimés compris : leur numéro reste pris).
 */
class StoreActeNaissanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $acte = $this->route('acteNaissance');

        $user = $this->user();
        $allowed = $acte instanceof ActeNaissance ? $user?->can('update', $acte) : $user?->can('create', ActeNaissance::class);

        return ($allowed ?? false) && (! $this->boolean('print') || $user->can('actes-naissance.deliver'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $acte = $this->route('acteNaissance');
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
                Rule::unique('actes_naissance', 'act_number')
                    ->where('mairie_id', $this->user()?->mairie_id)
                    ->where('centre_type', $this->input('centre_type'))
                    ->where('centre_name', $this->input('centre_name'))
                    ->where('act_year', $this->input('act_year'))
                    ->ignore($acte instanceof ActeNaissance ? $acte->getKey() : null),
            ],
            'act_year' => ['required', 'integer', 'min:1900', 'max:'.date('Y')],
            'nina' => ['nullable', 'string', 'regex:/^[0-9A-Z]{10,20}$/'],

            'first_names' => $text(150, true),
            'last_name' => $text(100, true),
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'birth_time' => ['nullable', 'date_format:H:i'],
            'birth_place' => $text(150, true),
            'gender' => ['required', Rule::enum(Genre::class)],

            'father_first_names' => $text(150),
            'father_last_name' => $text(100),
            'father_profession' => $text(100),
            'father_domicile' => $text(150),

            'mother_first_names' => $text(150, true),
            'mother_last_name' => $text(100, true),
            'mother_profession' => $text(100),
            'mother_domicile' => $text(150),

            'officer_name' => $text(150, true),
            'officer_title' => $text(150, true),
            'drawn_up_at' => ['required', 'date', 'after_or_equal:birth_date', 'before_or_equal:today'],

            // Bloc de certification de l'extrait (seulement à l'impression).
            'cert_officer_name' => ['exclude_unless:print,1', 'required', 'string', 'max:150'],
            'cert_officer_title' => ['exclude_unless:print,1', 'required', 'string', 'max:150'],
            'cert_centre_type' => ['exclude_unless:print,1', 'required', Rule::enum(CentreEtatCivilType::class)],
            'cert_centre_name' => ['exclude_unless:print,1', 'required', 'string', 'max:100'],
            'cert_delivered_at' => ['exclude_unless:print,1', 'required', 'date', 'before_or_equal:today'],
        ];
    }

    /** Champs de l'acte seulement (sans le bloc de certification). */
    public function acteData(): array
    {
        return collect($this->validated())->reject(fn ($value, $key) => str_starts_with($key, 'cert_'))->all();
    }

    /** Bloc de certification de l'extrait, aux noms de colonnes d'ExtraitNaissance. */
    public function extraitData(): array
    {
        return [
            'officer_name' => $this->validated('cert_officer_name'),
            'officer_title' => $this->validated('cert_officer_title'),
            'centre_type' => $this->validated('cert_centre_type'),
            'centre_name' => $this->validated('cert_centre_name'),
            'delivered_at' => $this->validated('cert_delivered_at'),
        ];
    }

    /**
     * L'acte est inscrit au registre de l'année où il est dressé : jamais avant la naissance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['birth_date', 'act_year'])) {
                    return;
                }
                if ((int) $this->input('act_year') < (int) date('Y', strtotime((string) $this->input('birth_date')))) {
                    $validator->errors()->add('act_year', 'L\'année du registre ne peut pas précéder l\'année de naissance.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'act_number.unique' => 'Ce numéro d\'acte existe déjà pour ce centre et cette année.',
            'nina.regex' => 'Le NINA ne contient que des chiffres et des lettres majuscules (10 à 20 caractères).',
            'birth_date.before_or_equal' => 'La date de naissance ne peut pas être dans le futur.',
            'birth_time.date_format' => 'L\'heure de naissance doit être au format HH:MM.',
            'drawn_up_at.after_or_equal' => 'L\'acte ne peut pas être dressé avant la naissance.',
            'drawn_up_at.before_or_equal' => 'La date d\'établissement ne peut pas être dans le futur.',
            'cert_delivered_at.before_or_equal' => 'La date de l\'extrait ne peut pas être dans le futur.',
        ];
    }

    public function attributes(): array
    {
        return [
            'region' => 'région', 'cercle' => 'cercle', 'arrondissement' => 'arrondissement', 'commune' => 'commune',
            'centre_type' => 'type de centre', 'centre_name' => 'nom du centre', 'act_number' => 'numéro de l\'acte',
            'act_year' => 'année du registre', 'nina' => 'NINA', 'first_names' => 'prénom(s)', 'last_name' => 'nom',
            'birth_date' => 'date de naissance', 'birth_time' => 'heure de naissance', 'birth_place' => 'localité de naissance',
            'gender' => 'sexe', 'father_first_names' => 'prénom(s) du père', 'father_last_name' => 'nom du père',
            'father_profession' => 'profession du père', 'father_domicile' => 'domicile du père',
            'mother_first_names' => 'prénom(s) de la mère', 'mother_last_name' => 'nom de la mère',
            'mother_profession' => 'profession de la mère', 'mother_domicile' => 'domicile de la mère',
            'officer_name' => 'officier de l\'état civil', 'officer_title' => 'qualité de l\'officier',
            'drawn_up_at' => 'date d\'établissement de l\'acte',
            'cert_officer_name' => 'officier qui délivre l\'extrait', 'cert_officer_title' => 'qualité de l\'officier qui délivre',
            'cert_centre_type' => 'type de centre de délivrance', 'cert_centre_name' => 'centre de délivrance',
            'cert_delivered_at' => 'date de l\'extrait',
        ];
    }

    /**
     * Moins de saisie : tout ce qui n'est pas tapé est complété. Sur un nouvel acte, l'en-tête, l'officier, la
     * localité de naissance viennent des réglages de la mairie ; le numéro suit le dernier du registre ; l'acte est
     * daté du jour. Le nom du père reprend celui de l'enfant, le domicile de la mère celui du père. À l'impression,
     * le bloc de certification vient des réglages (officier signataire). En correction, l'acte existant fait foi.
     */
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
        // « print » arrive en case de formulaire (« 1 ») ou en booléen JSON (true) : une seule forme pour les règles.
        $data['print'] = $this->boolean('print') ? '1' : '0';
        if (! empty($data['nina'])) {
            $data['nina'] = strtoupper(str_replace(' ', '', $data['nina']));
        }

        $acte = $this->route('acteNaissance');
        $mairie = $this->user()?->mairie;
        $fill = function (string $field, $value) use (&$data) {
            if (($data[$field] ?? null) === null && $value !== null && $value !== '') {
                $data[$field] = $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
            }
        };

        if ($acte instanceof ActeNaissance) {
            foreach (array_keys($this->rules()) as $field) {
                if (! str_starts_with($field, 'cert_')) {
                    $fill($field, $acte->getAttribute($field));
                }
            }
        } elseif ($mairie) {
            foreach (['region', 'cercle', 'arrondissement', 'commune', 'centre_type', 'centre_name', 'officer_name', 'officer_title'] as $field) {
                $fill($field, $mairie->getAttribute('civil_'.$field));
            }
            $fill('birth_place', $mairie->civil_birth_place);
            $fill('drawn_up_at', now()->toDateString());
            $fill('act_year', (int) date('Y', strtotime((string) $data['drawn_up_at'])));
            $fill('act_number', $this->nextNumber($data));
        }

        if (! empty($data['father_first_names'])) {
            $fill('father_last_name', $data['last_name'] ?? null);
        }
        $fill('mother_domicile', $data['father_domicile'] ?? null);

        if ($data['print'] === '1' && $mairie) {
            $fill('cert_officer_name', $mairie->civil_officer_name);
            $fill('cert_officer_title', $mairie->civil_officer_title);
            $fill('cert_centre_type', $mairie->civil_centre_type ?? ($data['centre_type'] ?? null));
            $fill('cert_centre_name', $mairie->civil_centre_name ?? ($data['centre_name'] ?? null));
            $fill('cert_delivered_at', now()->toDateString());
        }

        $this->merge($data);
    }

    /** Numéro suivant du registre (même mairie, même centre, même année) : 1 pour le premier acte. */
    private function nextNumber(array $data): ?string
    {
        if (empty($data['centre_name']) || empty($data['act_year'])) {
            return null;
        }

        $max = ActeNaissance::query()->withTrashed()
            ->where('centre_type', $data['centre_type'] ?? null)
            ->where('centre_name', $data['centre_name'])
            ->where('act_year', $data['act_year'])
            ->selectRaw('max(cast(act_number as unsigned)) as last')->value('last');

        return (string) (((int) $max) + 1);
    }
}
