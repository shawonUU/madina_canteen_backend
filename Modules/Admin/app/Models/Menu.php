<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    // protected static function newFactory(): MenuFactory
    // {
    //     // return MenuFactory::new();
    // }
}
