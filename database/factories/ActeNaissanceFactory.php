<?php

namespace Database\Factories;

use App\Enums\CentreEtatCivilType;
use App\Enums\Genre;
use App\Models\ActeNaissance;
use App\Models\Mairie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Données entièrement fictives.
 *
 * @extends Factory<ActeNaissance>
 */
class ActeNaissanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $birth = fake()->dateTimeBetween('-40 years', '-1 month');

        return [
            'mairie_id' => Mairie::factory(),
            'region' => 'District de Bamako',
            'cercle' => 'Bamako',
            'arrondissement' => null,
            'commune' => 'Commune IV',
            'centre_type' => CentreEtatCivilType::Principal->value,
            'centre_name' => 'Lafiabougou',
            'act_number' => (string) fake()->unique()->numberBetween(1, 99999),
            'act_year' => (int) $birth->format('Y'),
            'nina' => null,
            'first_names' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => $birth->format('Y-m-d'),
            'birth_time' => null,
            'birth_place' => 'Bamako',
            'gender' => fake()->randomElement(Genre::cases())->value,
            'father_first_names' => fake()->firstNameMale(),
            'father_last_name' => fake()->lastName(),
            'father_profession' => 'Commerçant',
            'father_domicile' => 'Bamako',
            'mother_first_names' => fake()->firstNameFemale(),
            'mother_last_name' => fake()->lastName(),
            'mother_profession' => 'Ménagère',
            'mother_domicile' => 'Bamako',
            'officer_name' => fake()->name(),
            'officer_title' => 'Officier de l\'état civil',
            'drawn_up_at' => $birth->format('Y-m-d'),
        ];
    }
}
