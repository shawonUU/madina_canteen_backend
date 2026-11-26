<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Reception\Database\Factories\GatePassPurposeFactory;

class GatePassPurpose extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    // protected static function newFactory(): GatePassPurposeFactory
    // {
    //     // return GatePassPurposeFactory::new();
    // }
}
