<?php

namespace Modules\Meal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Menu extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'menu_date' => 'date:Y-m-d',
    ];

    public function mealType()
    {
        return $this->belongsTo(
            MealType::class,
            'meal_type_id'
        );
    }

    public function items()
    {
        return $this->hasMany(
            MenuItem::class,
            'menu_id'
        );
    }
}