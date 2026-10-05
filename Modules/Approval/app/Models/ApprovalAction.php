<?php

namespace Modules\Approval\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Models\ApprovalRequestLevel;
use Modules\Approval\Models\User;

class ApprovalAction extends Model
{

    protected $guarded = [];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalRequest::class
        );
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalRequestLevel::class,
            'approval_request_level_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'action_by'
        );
    }
}
