<?php

namespace Modules\Meal\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Meal\Models\MealType;

class MealDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mealTypes = [
            [
                'name' => 'Breakfast',
                'slug' => 'breakfast',
                'description' => 'Morning meal',
                'booking_cutoff_time' => '08:00:00',
                'meal_rate' => 50.00,
                'status' => 'Active',
            ],
            [
                'name' => 'Lunch',
                'slug' => 'lunch',
                'description' => 'Afternoon meal',
                'booking_cutoff_time' => '11:00:00',
                'meal_rate' => 50.00,
                'status' => 'Active',
            ],
            [
                'name' => 'Dinner',
                'slug' => 'dinner',
                'description' => 'Evening meal',
                'booking_cutoff_time' => '18:00:00',
                'meal_rate' => 50.00,
                'status' => 'Active',
            ],
        ];

        foreach ($mealTypes as $mealType) {
            MealType::updateOrCreate(
                ['name' => $mealType['name']],
                $mealType
            );
        }
    }
}