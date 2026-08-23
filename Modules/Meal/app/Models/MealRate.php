<?php

namespace Modules\Meal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Meal\Database\Factories\MealRateFactory;

class MealRate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    protected $casts = [
        'effective_date' => 'date:Y-m-d',
        'rate' => 'decimal:2',
    ];

    public function mealType()
    {
        return $this->belongsTo(MealType::class);
    }

    // protected static function newFactory(): MealRateFactory
    // {
    //     // return MealRateFactory::new();
    // }
}
