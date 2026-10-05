<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Models\User;
use Modules\Canteen\Database\Seeders\MealDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([ MealDatabaseSeeder::class,]);
        $this->call([MenuSeeder::class,]);

        User::updateOrCreate(
            ['email' => 'sawonmiah@madina.co'],
            [
                'code' => getGenerateCode(User::class, 'code', 'USR', 8),
                'name' => 'Admin User',
                'password' => Hash::make('12345678'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'mojmeen@gmail.com'],
            [
                'code' => getGenerateCode(User::class, 'code', 'USR', 8),
                'name' => 'Mojmeen Akther',
                'password' => Hash::make('12345678'),
            ]
        );
        User::updateOrCreate(
            ['email' => 'hr@gmail.com'],
            [
                'code' => getGenerateCode(User::class, 'code', 'USR', 8),
                'name' => 'Jannatul Ferdous',
                'password' => Hash::make('12345678'),
            ]
        );
    }
}