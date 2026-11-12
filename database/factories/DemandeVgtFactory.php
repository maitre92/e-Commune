<?php

namespace Database\Factories;

use App\Models\DemandeVgt;
use App\Models\Mairie;
use App\Models\Moto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandeVgt>
 */
class DemandeVgtFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mairie_id' => Mairie::factory(),
            // La moto appartient toujours à la même mairie que sa vignette.
            'moto_id' => fn (array $attributes) => Moto::factory()->state(['mairie_id' => $attributes['mairie_id']]),
            'vgt_year' => (int) date('Y'),
            'status' => 'en_attente',
            'base_amount' => 6000,
            'is_late' => false,
            'surcharge_amount' => 0,
        ];
    }
}
