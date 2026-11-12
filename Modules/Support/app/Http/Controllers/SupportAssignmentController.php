<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Models\SupportTicketHistory;

class SupportAssignmentController extends Controller
{
    public function assign(Request $request, int $id): JsonResponse
    {
        abort_unless(
            $request->user()->can('support.ticket.manage'),
            403,
            'You are not allowed to assign tickets.'
        );

        $validated = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $ticket = SupportTicket::findOrFail($id);

        DB::transaction(function () use ($ticket, $validated, $request) {
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

        $ticket = SupportTicket::findOrFail($id);

        DB::transaction(function () use ($ticket, $validated, $request) {
            $oldStatus = $ticket->status;
            $newStatus = $validated['status'];

            if ($oldStatus === $newStatus) {
                return;
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
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully.',
            'data' => $ticket->fresh(),
        ]);
    }
}