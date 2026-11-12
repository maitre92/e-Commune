<?php

namespace App\Http\Requests\Web;

use App\Models\DemandeVgt;
use App\Models\Moto;
use App\Models\TarifVgt;
use App\Services\Vgt\DemandeVgtPricing;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Guichet vignette, au plus simple : le numéro de châssis (CH) relevé sur l'ancienne vignette ou les papiers de
 * la moto suffit. La cylindrée (catégorie de tarif de la mairie) est choisie d'un geste, présélectionnée si la moto est connue. Facultatifs, le nom du
 * détenteur imprimé au verso, et l'année (l'année en cours par défaut ; une année passée = arriéré).
 */
class GuichetVignetteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', DemandeVgt::class) && $user->can('demandes-vgt.confirm_payment')
            && ($this->knownMoto() !== null || $user->can('motos.create'));
    }

    public function knownMoto(): ?Moto
    {
        return Moto::where('chassis_number', Moto::normalizeChassis($this->input('chassis_number')))->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Moto inconnue de la mairie : le CH ne doit pas appartenir à une autre mairie (unicité nationale).
            'chassis_number' => ['required', 'string', 'max:50', ...($this->knownMoto() ? [] : [Rule::unique('motos', 'chassis_number')])],
            'vgt_year' => ['required', 'integer', 'min:2000', 'max:'.((int) date('Y') + 1)],
            // Catégorie (cylindrée) parmi celles réglées par la mairie ; obligatoire s'il y en a plusieurs et que la
            // moto n'en a pas encore.
            'type_or_brand' => [
                Rule::requiredIf(fn () => $this->activeLabels()->count() > 1 && ! $this->knownCategory()),
                'nullable', 'string', Rule::in($this->activeLabels()->all()),
            ],
            'holder_name' => ['nullable', 'string', 'max:150'],
            'holder_phone' => ['nullable', 'string', 'regex:/^\+\d{8,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'chassis_number.required' => 'Saisissez le N° Engin de la moto.',
            'chassis_number.unique' => 'Ce N° Engin est déjà enregistré dans une autre mairie.',
            'holder_phone.regex' => 'Numéro de téléphone du détenteur invalide (ex. 70 00 12 34).',
            'type_or_brand.required' => 'Choisissez la cylindrée de la moto.',
            'type_or_brand.in' => 'Cette catégorie de cylindrée n\'est pas proposée par la mairie.',
        ];
    }

    public function attributes(): array
    {
        return ['chassis_number' => 'N° Engin', 'vgt_year' => 'année', 'type_or_brand' => 'catégorie', 'holder_name' => 'nom du détenteur'];
    }

    /** Tarif choisi au guichet, sinon celui de la catégorie déjà connue de la moto. */
    public function tarif(): ?TarifVgt
    {
        $mairieId = $this->knownMoto()?->mairie_id ?? $this->user()?->mairie_id;

        return $mairieId ? DemandeVgtPricing::tarifFor($mairieId, $this->validated('type_or_brand') ?? $this->knownMoto()?->type_or_brand) : null;
    }

    /** @return Collection<int, string> */
    private function activeLabels(): Collection
    {
        $mairieId = $this->user()?->mairie_id;

        return $this->labels ??= $mairieId ? TarifVgt::gridFor($mairieId, activeOnly: true)->pluck('type_or_brand') : collect();
    }

    /** La moto connue a déjà une catégorie de la mairie. */
    private function knownCategory(): bool
    {
        $category = $this->knownMoto()?->type_or_brand;

        return $category !== null && $this->activeLabels()->contains($category);
    }

    /** @var Collection<int, string>|null */
    private ?Collection $labels = null;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'chassis_number' => Moto::normalizeChassis($this->input('chassis_number')),
            'vgt_year' => $this->filled('vgt_year') ? $this->input('vgt_year') : (int) date('Y'),
            // Une seule catégorie active : elle s'applique sans rien choisir.
            'type_or_brand' => $this->filled('type_or_brand') ? trim((string) $this->input('type_or_brand')) : ($this->activeLabels()->count() === 1 ? $this->activeLabels()->first() : null),
            'holder_name' => $this->filled('holder_name') ? trim((string) $this->input('holder_name')) : null,
            // Numéro saisi comme on le dit (« 70 00 12 34 ») : rangé en E.164, illisible = refusé.
            'holder_phone' => $this->filled('holder_phone') ? (PhoneNumber::normalize((string) $this->input('holder_phone')) ?? (string) $this->input('holder_phone')) : null,
        ]);
    }
}
