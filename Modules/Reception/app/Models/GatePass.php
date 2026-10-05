<?php

namespace Modules\Reception\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Admin\Models\User;
use Modules\Approval\Models\ApprovalRequest;
// use Modules\Reception\Database\Factories\GatePassFactory;

class GatePass extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GatePassItem::class);
    }

    public function approvalRequest()
    {
        return $this->morphOne(
            ApprovalRequest::class,
            'document'
        );
    }

    // protected static function newFactory(): GatePassFactory
    // {
    //     // return GatePassFactory::new();
    // }
}
