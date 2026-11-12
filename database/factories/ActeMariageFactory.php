<?php

namespace Database\Factories;

use App\Enums\CentreEtatCivilType;
use App\Enums\OptionMatrimoniale;
use App\Enums\RegimeMatrimonial;
use App\Models\ActeMariage;
use App\Models\Mairie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Données entièrement fictives.
 *
 * @extends Factory<ActeMariage>
 */
class ActeMariageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $celebrated = fake()->dateTimeBetween('-10 years', '-1 week');
        $husbandLast = fake()->lastName();
        $wifeLast = fake()->lastName();

        return [
            'mairie_id' => Mairie::factory(),
            'region' => 'Kayes',
            'cercle' => 'Kayes',
            'arrondissement' => null,
            'commune' => 'Commune de démonstration',
            'centre_type' => CentreEtatCivilType::Principal->value,
            'centre_name' => 'Centre de démonstration',
            'act_number' => (string) fake()->unique()->numberBetween(1, 99999),
            'act_year' => (int) $celebrated->format('Y'),
            'husband_first_names' => fake()->firstNameMale(),
            'husband_last_name' => $husbandLast,
            'husband_birth_date' => fake()->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            'husband_birth_place' => 'Bamako',
            'husband_profession' => 'Enseignant',
            'husband_domicile' => 'Kayes',
            'wife_first_names' => fake()->firstNameFemale(),
            'wife_last_name' => $wifeLast,
            'wife_birth_date' => fake()->dateTimeBetween('-50 years', '-19 years')->format('Y-m-d'),
            'wife_birth_place' => 'Kati',
            'wife_profession' => 'Ménagère',
            'wife_domicile' => 'Kayes',
            'husband_father_first_names' => fake()->firstNameMale(),
            'husband_father_last_name' => $husbandLast,
            'husband_mother_first_names' => fake()->firstNameFemale(),
            'husband_mother_last_name' => fake()->lastName(),
            'wife_father_first_names' => fake()->firstNameMale(),
            'wife_father_last_name' => $wifeLast,
            'wife_mother_first_names' => fake()->firstNameFemale(),
            'wife_mother_last_name' => fake()->lastName(),
            'declared_at' => $celebrated->format('Y-m-d'),
            'celebrated_at' => $celebrated->format('Y-m-d'),
            'marriage_place' => 'Kayes',
            'regime' => RegimeMatrimonial::SeparationBiens->value,
            'option' => OptionMatrimoniale::Monogamie->value,
            'officer_name' => fake()->name(),
            'officer_title' => 'Maire',
            'drawn_up_at' => $celebrated->format('Y-m-d'),
        ];
    }
}
