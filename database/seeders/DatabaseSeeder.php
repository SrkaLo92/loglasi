<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductSeeder::class);
        $this->call(PayeeSeeder::class);

        // Local development only; the real data comes from the WordPress import.
        if (app()->isLocal()) {
            $this->call(CatalogSeeder::class);

            User::factory()->admin()->create([
                'name' => 'Admin',
                'email' => 'admin@loglasi.rs',
                'slug' => 'admin',
            ]);
        }
    }
}
