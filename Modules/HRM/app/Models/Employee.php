<?php

namespace Modules\HRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Modules\Admin\Models\User;
use Spatie\Permission\Traits\HasRoles;
// use Modules\Employee\Database\Factories\EmployeeFactory;

class Employee extends Model
{
    use HasApiTokens, HasFactory, HasRoles;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    // protected static function newFactory(): EmployeeFactory
    // {
    //     // return EmployeeFactory::new();
    // }
}
