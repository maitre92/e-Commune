<?php

namespace Database\Factories;

use App\Models\Mairie;
use App\Models\Moto;
use App\Models\Proprietaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Moto>
 */
class MotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mairie_id' => Mairie::factory(),
            // Le propriétaire appartient toujours à la même mairie que sa moto.
            'proprietaire_id' => fn (array $attributes) => Proprietaire::factory()->state(['mairie_id' => $attributes['mairie_id']]),
            'chassis_number' => strtoupper(fake()->unique()->bothify('LX##??########')),
            'plate_number' => strtoupper(fake()->unique()->bothify('?? #### ??')),
            'color' => fake()->safeColorName(),
            'type_or_brand' => fake()->randomElement(['Sanili', 'Sanya', 'Djakarta', 'Yamaha', 'Sagitar']),
            'vgt_year' => (int) date('Y'),
            'has_sale_certificate' => false,
            'has_witness' => false,
        ];
    }

    public function withSaleCertificate(): static
    {
        return $this->state(fn () => [
            'has_sale_certificate' => true,
            'seller_first_name' => fake()->firstName(),
            'seller_last_name' => fake()->lastName(),
            'seller_phone' => '+2237'.fake()->numerify('#######'),
            'seller_address' => fake()->streetAddress(),
        ]);
    }
}
