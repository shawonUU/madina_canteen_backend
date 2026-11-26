<?php

namespace App\Services;

use App\Models\RecordLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Admin\Models\User;
use Modules\Approval\Services\ApprovalService;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Services\MeetingAccess;
use Modules\Support\Services\SupportAccess;

class RecordLogAccess
{
    public function __construct(private AccessService $access) {}

    public function resolve(string $resource, string $id): Model
    {
        $meta = config('audit.resources')[$resource] ?? null;
        abort_unless($meta, 404, 'Record type not found.');
        $query = $meta[0]::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($meta[0]), true)) $query->withTrashed();
        $record = $query->find($id);
        if ($record) return $record;
        $log = RecordLog::where('resource', $resource)->where('record_id', $id)->latest('id')->firstOrFail();
        $record = new $meta[0];
        $record->setRawAttributes($log->new_values ?: $log->old_values ?: []);
        return $record;
    }

    public function mayRead(User $user, string $resource): bool
    {
        $meta = config('audit.resources')[$resource] ?? null;
        if (!$meta) return false;
        $allowed = $this->access->can($user, $meta[1]);
        if ($resource === 'users') $allowed = $allowed || $this->access->can($user, 'user_access.view');
        if (in_array($resource, ['employee_meals', 'employee_meal_items'], true)) $allowed = $allowed || $this->access->can($user, 'meal_serve.view') || $this->access->can($user, 'meal_report.employee.view') || $this->access->can($user, 'meal_report.cost.view');
        if ($resource === 'meetings') $allowed = $allowed || $this->access->can($user, 'meeting.report.view') || $this->access->can($user, 'meeting.attendance.report');
        if (str_starts_with($resource, 'support_ticket')) $allowed = $allowed || $this->access->can($user, 'support.report.view');
        if ($resource === 'support_tickets') $allowed = $allowed || $this->access->can($user, 'support.dashboard.view');
        if ($resource === 'meeting_attendances') $allowed = $allowed || $this->access->can($user, 'meeting.attendance.report');
        if ($resource === 'meeting_participants') $allowed = $allowed || $this->access->can($user, 'meeting.attendance.view') || $this->access->can($user, 'meeting.attendance.report');
        return $allowed;
    }

    public function authorize(User $user, string $resource, Model $record, bool $parent = false): void
    {
        if ($this->access->can($user, 'audit.view-all')) return;
        $meta = config('audit.resources')[$resource];
        if (isset($meta[2], $meta[3])) {
            $ownInvitation = $resource === 'meeting_participants' && (int) $record->employee_id === (int) $user->employee_id && $user->employee_id && $this->access->can($user, 'meeting.invitation.view');
            abort_unless($this->mayRead($user, $resource) || $ownInvitation, 403, 'You cannot view this record history.');
            if ($resource === 'support_ticket_replies' && $record->is_internal) $this->access->authorize($user, 'support.ticket.internal');
            $this->authorize($user, $meta[2], $this->resolve($meta[2], (string) $record->{$meta[3]}), true);
            return;
        }
        if ($resource === 'approval_requests') {
            abort_unless(app(ApprovalService::class)->canView($record, $user), 403, 'You cannot access this approval.');
            return;
        }
        $ownMeal = $resource === 'employee_meals' && $user->employee_id && (int) $record->employee_id === (int) $user->employee_id;
        $dashboardMeal = $resource === 'employee_meals' && $this->access->can($user, 'dashboard.view-all');
        $allowed = $parent || $ownMeal || $dashboardMeal || $this->mayRead($user, $resource);
        abort_unless($allowed, 403, 'You do not have permission to view this record history.');
        if ($resource === 'employee_meals') {
            $canViewAll = $dashboardMeal || $this->access->can($user, 'meal_booking.manage-on-behalf') || $this->access->can($user, 'meal_serve.view') || $this->access->can($user, 'meal_report.employee.view') || $this->access->can($user, 'meal_report.cost.view');
            abort_unless($canViewAll || ($user->employee_id && (int) $record->employee_id === (int) $user->employee_id) || (int) $record->created_by === (int) $user->id, 403, 'You cannot access this meal booking history.');
        }
        if ($resource === 'support_tickets') abort_unless(SupportAccess::canView($user, $record), 403, 'You cannot access this ticket.');
        if ($resource === 'meetings') {
            $visible = MeetingAccess::visibleTo(Meeting::withTrashed(), $user)->whereKey($record->id)->exists();
            if (!$visible && !$record->exists) $visible = (int) $record->organizer_id === (int) $user->id || (int) $record->chairperson_id === (int) $user->id || $this->access->can($user, 'meeting.view-all');
            abort_unless($visible, 403, 'You cannot access this meeting.');
        }
    }
}
