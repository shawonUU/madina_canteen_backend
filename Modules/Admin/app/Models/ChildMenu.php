<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Admin\Database\Factories\ChildMenuFactory;

class ChildMenu extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    // protected static function newFactory(): ChildMenuFactory
    // {
    //     // return ChildMenuFactory::new();
    // }
}
