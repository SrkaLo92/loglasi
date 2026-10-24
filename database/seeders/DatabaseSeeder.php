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

        // Samo za lokalni razvoj; pravi podaci dolaze uvozom iz WordPress-a.
        if (app()->isLocal()) {
            $this->call(KatalogSeeder::class);

            User::factory()->admin()->create([
                'name' => 'Admin',
                'email' => 'admin@loglasi.rs',
                'slug' => 'admin',
            ]);
        }
    }
}
