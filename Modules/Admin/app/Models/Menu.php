<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Canteen\Models\MenuItem;
// use Modules\Admin\Database\Factories\MenuFactory;

class Menu extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
    
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function childMenus()
    {
        return $this->hasMany(ChildMenu::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }

    // protected static function newFactory(): MenuFactory
    // {
    //     // return MenuFactory::new();
    // }
}
