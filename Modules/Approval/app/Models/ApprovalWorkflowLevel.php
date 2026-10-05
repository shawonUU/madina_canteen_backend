<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Approval\Models\ApprovalWorkflow;

class ApprovalWorkflowLevel extends Model
{

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'can_reject' => 'boolean',
        'can_return' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalWorkflow::class,
            'workflow_id'
        );
    }
}
