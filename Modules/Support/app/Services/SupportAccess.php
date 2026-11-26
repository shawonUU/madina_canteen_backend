<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Admin\Models\User;
use Modules\Support\Models\SupportTicket;

class SupportAccess
{
    public static function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('support.ticket.view-all')) {
            return $query;
        }
        return $query->where(function (Builder $query) use ($user) {
            $query->where('submitted_by', $user->id)->orWhere('assigned_to', $user->id);
        });
    }

    public static function canView(User $user, SupportTicket $ticket): bool
    {
        return $user->can('support.ticket.view-all')
            || (int) $ticket->submitted_by === (int) $user->id
            || (int) $ticket->assigned_to === (int) $user->id;
    }

    public static function authorize(User $user, SupportTicket $ticket): void
    {
        abort_unless(self::canView($user, $ticket), 403, 'You cannot access this ticket.');
    }
}
