<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Admin\Database\Factories\ModuleFactory;

class Module extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }

    // protected static function newFactory(): ModuleFactory
    // {
    //     // return ModuleFactory::new();
    // }
}
