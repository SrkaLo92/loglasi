<?php

namespace Database\Factories;

use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Firm>
 */
class FirmFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress().', Loznica',
            'biznis_until' => now()->addYear(),
        ];
    }
}
