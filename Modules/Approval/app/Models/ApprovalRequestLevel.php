<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Admin\Models\User;
use Modules\Approval\Models\ApprovalRequest;

class ApprovalRequestLevel extends Model
{

    protected $guarded = [];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalRequest::class
        );
    }

    public function actionUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
