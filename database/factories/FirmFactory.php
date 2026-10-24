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
        ];
    }

    /** Firm with an active package (default: Business Start, monthly, ends in a month). Attributes given to create() win. */
    public function package(string $package = 'start', string $period = 'monthly'): static
    {
        return $this->state(fn () => [
            'package' => $package,
            'package_period' => $period,
            'package_starts_at' => now(),
            'package_ends_at' => $period === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);
    }

    /** One of the first 30 firms in its free year. */
    public function freeYear(): static
    {
        return $this->state(fn () => [
            'free_program' => true,
            'founding_free' => true,
            'package_starts_at' => now(),
            'package_ends_at' => now()->addYear(),
        ]);
    }
}
