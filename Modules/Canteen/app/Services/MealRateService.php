<?php

namespace Modules\Canteen\Services;

use Modules\Canteen\Models\MealRate;
use Modules\Canteen\Models\MealType;

class MealRateService
{
    public function forDate(MealType $mealType, string $date): float
    {
        $rate = MealRate::where('meal_type_id', $mealType->id)
            ->where('status', 'Active')
            ->where(fn ($query) => $query->whereDate('effective_date', '<=', $date)->orWhereNull('effective_date'))
            ->orderByDesc('effective_date')->orderByDesc('id')->value('rate');
        return (float) ($rate ?? $mealType->meal_rate ?? 0);
    }
}
