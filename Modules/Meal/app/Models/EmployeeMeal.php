<?php

namespace Modules\Meal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\Models\User;
use Modules\Auth\Models\Employee;
// use Modules\Meal\Database\Factories\EmployeeMealFactory;

class EmployeeMeal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function employee(){
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function mealType()
    {
        return $this->belongsTo( MealType::class, 'meal_type_id' );
    }

    public function menu()
    {
        return $this->belongsTo( Menu::class, 'menu_id' );
    }

    public function items()
    {
        return $this->hasMany(
            EmployeeMealItem::class,
            'employee_meal_id'
        );
    }

    // protected static function newFactory(): EmployeeMealFactory
    // {
    //     // return EmployeeMealFactory::new();
    // }
}
