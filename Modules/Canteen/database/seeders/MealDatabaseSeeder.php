<?php

namespace Modules\Canteen\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Canteen\Models\MealType;

class MealDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mealTypes = [
            // [
            //     'code' => getGenerateCode(MealType::class, 'code', 'MT', 8),
            //     'name' => 'Breakfast',
            //     'slug' => 'breakfast',
            //     'description' => 'Morning meal',
            //     'booking_cutoff_time' => '08:00:00',
            //     'meal_rate' => 50.00,
            //     'status' => 'Active',
            // ],
            [
                'code' => getGenerateCode(MealType::class, 'code', 'MT', 8),
                'name' => 'Lunch',
                'slug' => 'lunch',
                'description' => 'Afternoon meal',
                'booking_cutoff_time' => '11:00:00',
                'meal_rate' => 50.00,
                'status' => 'Active',
            ],
            // [
            //     'code' => getGenerateCode(MealType::class, 'code', 'MT', 8),
            //     'name' => 'Dinner',
            //     'slug' => 'dinner',
            //     'description' => 'Evening meal',
            //     'booking_cutoff_time' => '18:00:00',
            //     'meal_rate' => 50.00,
            //     'status' => 'Active',
            // ],
        ];

        foreach ($mealTypes as $mealType) {
            MealType::updateOrCreate(
                ['code' => getGenerateCode(MealType::class, 'code', 'MT', 8), 'name' => $mealType['name']],
                $mealType
            );
        }
    }
}