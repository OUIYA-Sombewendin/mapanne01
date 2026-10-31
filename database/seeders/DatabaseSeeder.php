<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([LocationSeeder::class, ServiceSeeder::class, PartCategorySeeder::class]);

        // Hors développement, le mot de passe admin doit être fourni (ADMIN_PASSWORD dans .env).
        $password = env('ADMIN_PASSWORD');
        if (! $password && ! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Définissez ADMIN_PASSWORD dans .env avant de lancer le seeder en production.');
        }

        User::factory()->admin()->create([
            'name' => 'Administrateur MaPanne',
            'phone' => '+22670000000',
            'email' => 'admin@mapanne.test',
            'password' => $password ?: 'password',
        ]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
