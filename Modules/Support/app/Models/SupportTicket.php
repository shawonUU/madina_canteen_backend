<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Admin\Models\User;

class SupportTicket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'open',
        'assigned',
        'in_progress',
        'waiting_for_user',
        'resolved',
        'closed',
        'reopened',
    ];

    public const PRIORITIES = [
        'low',
        'medium',
        'high',
        'critical',
    ];

    public const ISSUE_TYPES = [
        'bug',
        'error',
        'access_request',
        'how_to',
        'other',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by'
        );
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SupportTicketHistory::class, 'ticket_id');
    }
}