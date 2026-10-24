<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('lozinka123'),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'account_type' => AccountType::Private,
            'phone' => '+38164'.fake()->unique()->numerify('#######'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function phoneVerified(): static
    {
        return $this->state(fn () => ['phone_verified_at' => now()]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function moderator(): static
    {
        return $this->state(fn () => ['role' => Role::Moderator]);
    }

    /** Firm account with a profile and an active Business Max package (unlimited listings). */
    public function firm(): static
    {
        return $this->state(fn () => ['account_type' => AccountType::Firm])
            ->afterCreating(fn (User $user) => Firm::factory()->package('max')->for($user)->create());
    }
}
