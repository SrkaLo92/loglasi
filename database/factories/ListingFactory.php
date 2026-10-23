<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::ucfirst(fake()->words(4, true));

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'location_id' => Location::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraphs(2, true),
            'price' => fake()->numberBetween(10, 20000),
            'currency' => Currency::EUR,
            'status' => ListingStatus::Published,
            'published_at' => now(),
            'bumped_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ListingStatus::Pending, 'published_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);
    }

    public function featured(int $days = 7): static
    {
        return $this->state(fn () => ['featured_until' => now()->addDays($days)]);
    }

    public function rsd(int $amount): static
    {
        return $this->state(fn () => ['price' => $amount, 'currency' => Currency::RSD]);
    }

    public function negotiable(): static
    {
        return $this->state(fn () => ['price' => null]);
    }
}
