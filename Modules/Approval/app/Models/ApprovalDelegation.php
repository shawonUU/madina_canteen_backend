<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Approval\Models\User;

class ApprovalDelegation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'from_user_id'
        );
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'to_user_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
