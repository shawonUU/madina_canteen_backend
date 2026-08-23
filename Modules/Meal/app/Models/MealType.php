<?php

namespace Modules\Meal\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealType extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function mealRates()
    {
        return $this->hasMany(MealRate::class);
    }
}