<?php

namespace Modules\Meal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Meal\Database\Factories\MenuItemFactory;

class MenuItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function menu()
    {
        return $this->belongsTo(
            Menu::class,
            'menu_id'
        );
    }

    public function alternativeOf()
    {
        return $this->belongsTo(
            MenuItem::class,
            'alternative_of'
        );
    }

    public function alternatives()
    {
        return $this->hasMany(
            MenuItem::class,
            'alternative_of'
        );
    }

}
