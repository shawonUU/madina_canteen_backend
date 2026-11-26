<?php

namespace App\Http\Middleware;

use App\Services\AccessService;
use Closure;
use Illuminate\Http\Request;

class EnforceApiAccess
{
    public function __construct(private AccessService $access) {}

    public function handle(Request $request, Closure $next)
    {
        $this->access->reset();
        $actionName = $request->route()?->getActionName() ?? '';
        [$controller, $method] = array_pad(explode('@', $actionName), 2, '');
        $controller = class_basename($controller);
        if ($controller === 'AuthController' && in_array($method, ['login', 'register', 'forgotPassword', 'resetPassword'], true)) return $next($request);
        if ($actionName === 'Closure') return $next($request);
        $user = $request->user();
        abort_unless($user && $user->status === 'Active', 401, 'Please sign in to continue.');

        $resource = config('access.controllers')[$controller] ?? null;
        $action = match (true) {
            $request->isMethod('GET'), $request->isMethod('HEAD') => 'view',
            $request->isMethod('DELETE') => 'delete',
            in_array($method, ['store', 'bookMeal', 'bookMeals', 'assignPermission'], true) => 'create',
            default => 'update',
        };
        if ($controller === 'UserPermissionController') $action = 'update';
        if ($controller === 'EmployeeMealController' && $method === 'cancelBooking') $action = 'delete';
        if ($controller === 'EmployeeMealController' && in_array($method, ['serveMeal', 'todaysBookedMeals'], true)) $resource = 'meal_serve';
        if ($controller === 'MealMenuController' && $method === 'approvedMenus') $resource = 'meal_booking';
        if ($controller === 'MealReportController') {
            $resource = match ($method) { 'daily' => 'meal_report.daily', 'employeeBookings' => 'meal_report.employee', 'itemConsumption' => 'meal_report.item', 'cost' => 'meal_report.cost', default => 'meal_report.daily' };
        }
        if ($controller === 'SupportAssignmentController') {
            $this->access->authorize($user, in_array($method, ['assign', 'assignees'], true) ? 'support.ticket.assign' : 'support.ticket.manage');
            $resource = null;
        }
        if ($controller === 'MeetingMinuteController' && $method === 'approve') {
            $this->access->authorize($user, 'meeting.minutes.approve');
            $resource = null;
        }
        if ($controller === 'SupportTicketReplyController' && $method === 'store') $action = 'update';
        if ($controller === 'MeetingParticipantController' && in_array($method, ['invitations', 'respond', 'myInvitations', 'updateInvitationStatus'], true)) $resource = 'meeting.invitation';

        // Option lists share minimal data across permitted modules; controllers still control record visibility.
        $lookup = $request->isMethod('GET') && (
            ($controller === 'MealTypeController' && $method === 'index' && ($this->access->hasModule($user, 'canteen') || $this->access->hasModule($user, 'reports')))
            || ($controller === 'GatePassPurposeController' && $method === 'index' && $this->access->can($user, 'gate_pass.create'))
            || ($controller === 'MealMenuController' && $method === 'todayMenus' && $this->access->can($user, 'meal_booking.view'))
            || ($controller === 'MeetingController' && $method === 'index' && $request->boolean('options') && $this->access->hasModule($user, 'meeting'))
            || ($controller === 'MeetingParticipantController' && $method === 'index' && $this->access->can($user, 'meeting.attendance.view'))
        );
        if ($resource && !$lookup) $this->access->authorize($user, $resource.'.'.$action);

        if ($resource) {
            $location = config('access.resources')[$resource] ?? null;
            if ($resource === 'meeting' && $method === 'store') $location = ['meeting', 'meeting-scheduling', 'create-meeting'];
            if ($resource === 'support.ticket' && $method === 'store') $location = ['support', 'create-ticket', null];
            if ($location) {
                $context = $this->access->context($location);
                abort_unless($context, 409, 'This menu is not configured. Run the menu update seeder.');
                $request->attributes->set('navigation_context', $context);
            }
        }
        return $next($request);
    }
}
