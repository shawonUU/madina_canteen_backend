<?php

namespace Modules\Approval\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Approval\Models\ApprovalWorkflowLevel;
use Modules\Approval\Models\User;

class ApprovalWorkflow extends Model
{

    protected $guarded = [];

    public function levels(): HasMany
    {
        return $this->hasMany(
            ApprovalWorkflowLevel::class,
            'workflow_id'
        )->orderBy('level_no');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
