<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Modules\Auth\Models\User;
use Modules\Meal\Database\Seeders\MealDatabaseSeeder;

use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MealDatabaseSeeder::class,
        ]);

        User::updateOrCreate(
            ['email' => 'sawonmiah@madina.co'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('12345678'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('12345678'),
            ]
        );
    }
}