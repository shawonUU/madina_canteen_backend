<?php
namespace Modules\Canteen\Models;
use Illuminate\Database\Eloquent\Model;
class MealClosing extends Model
{
    protected $guarded = [];
    protected $casts = ['meal_date' => 'date', 'closed_at' => 'datetime'];
    public function mealType() { return $this->belongsTo(MealType::class); }
}
