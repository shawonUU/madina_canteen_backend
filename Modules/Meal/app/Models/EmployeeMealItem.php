<?php

namespace Modules\Meal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Meal\Database\Factories\EmployeeMealItemFactory;

class EmployeeMealItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function menuItem()
    {
        return $this->belongsTo(
            MenuItem::class,
            'menu_item_id'
        );
    }

    public function employeeMeal()
    {
        return $this->belongsTo(
            EmployeeMeal::class,
            'employee_meal_id'
        );
    }

    // protected static function newFactory(): EmployeeMealItemFactory
    // {
    //     // return EmployeeMealItemFactory::new();
    // }
}
