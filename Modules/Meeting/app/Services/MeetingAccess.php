<?php

namespace Modules\Meeting\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Admin\Models\User;
use Modules\Meeting\Models\Meeting;

class MeetingAccess
{
    public static function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('meeting.view-all') || $user->can('meeting.manage-all')) {
            return $query;
        }
        return $query->where(function (Builder $query) use ($user) {
            $query->where('organizer_id', $user->id)->orWhere('chairperson_id', $user->id);
            if ($user->employee_id) {
                $query->orWhereHas('participants', fn (Builder $q) => $q->where('employee_id', $user->employee_id));
            }
        });
    }

    public static function canView(User $user, Meeting $meeting): bool
    {
        return self::visibleTo(Meeting::query(), $user)->whereKey($meeting->id)->exists();
    }

    public static function authorizeView(User $user, Meeting $meeting): void
    {
        abort_unless(self::canView($user, $meeting), 403, 'You cannot access this meeting.');
    }

    public static function canManage(User $user, Meeting $meeting): bool
    {
        return $user->can('meeting.manage-all') || (int) $meeting->organizer_id === (int) $user->id || (int) $meeting->chairperson_id === (int) $user->id;
    }

    public static function authorizeManage(User $user, Meeting $meeting): void
    {
        abort_unless(self::canManage($user, $meeting), 403, 'You cannot manage this meeting.');
    }
}
