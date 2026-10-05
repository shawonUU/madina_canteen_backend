<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Admin\Models\User;
// use Modules\Reception\Database\Factories\GatePassItemFactory;

class GatePassItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function gatePass(): BelongsTo
    {
        return $this->belongsTo(GatePass::class);
    }

    // protected static function newFactory(): GatePassItemFactory
    // {
    //     // return GatePassItemFactory::new();
    // }
}
