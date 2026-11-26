<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Models\SupportTicketHistory;
use Modules\Support\Services\SupportAccess;
use Modules\Admin\Models\User;

class SupportAssignmentController extends Controller
{
    public function assignees(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('support.ticket.assign'), 403, 'You cannot assign tickets.');
        $users = User::query()->where('status', 'Active')->select(['id', 'name'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . $request->input('search') . '%'))
            ->orderBy('name')->get();
        return response()->json(['data' => $users]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->can('support.ticket.assign'), 403, 'You are not allowed to assign tickets.');

        $validated = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', 'Active'),
            ],
        ]);

        $ticket = DB::transaction(function () use ($id, $validated, $request) {
            $ticket = SupportTicket::whereKey($id)->lockForUpdate()->firstOrFail();
            SupportAccess::authorize($request->user(), $ticket);
            $oldAssignee = $ticket->assigned_to;
            $newAssignee = (int) $validated['assigned_to'];

            if ((int) $oldAssignee !== $newAssignee) {
                $ticket->assigned_to = $newAssignee;

                if ($ticket->status === 'open') {
                    $ticket->status = 'assigned';
                }

                $ticket->save();

                SupportTicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'changed_by' => $request->user()->id,
                    'field_name' => 'assigned_to',
                    'old_value' => $oldAssignee,
                    'new_value' => $newAssignee,
                    'note' => 'Ticket assigned to support staff.',
                ]);
            }
            return $ticket;
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully.',
            'data' => $ticket->fresh()->load('assignee:id,name'),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        abort_unless(
            $request->user()->can('support.ticket.manage'),
            403,
            'You are not allowed to update ticket status.'
        );

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(SupportTicket::STATUSES),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket = DB::transaction(function () use ($id, $validated, $request) {
            $ticket = SupportTicket::whereKey($id)->lockForUpdate()->firstOrFail();
            SupportAccess::authorize($request->user(), $ticket);
            $oldStatus = $ticket->status;
            $newStatus = $validated['status'];

            if ($oldStatus === $newStatus) {
                return $ticket;
            }

            $ticket->status = $newStatus;

            if ($newStatus === 'resolved') {
                $ticket->resolved_at = now();
                $ticket->closed_at = null;
            } elseif ($newStatus === 'closed') {
                $ticket->closed_at = now();

                if (!$ticket->resolved_at) {
                    $ticket->resolved_at = now();
                }
            } elseif ($newStatus === 'reopened') {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            } else {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }

            $ticket->save();

            SupportTicketHistory::create([
                'ticket_id' => $ticket->id,
                'changed_by' => $request->user()->id,
                'field_name' => 'status',
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
                'note' => $validated['note'] ?? null,
            ]);
            return $ticket;
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully.',
            'data' => $ticket->fresh(),
        ]);
    }
}