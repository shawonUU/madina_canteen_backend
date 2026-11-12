<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Models\SupportTicketHistory;
use Modules\Support\Models\SupportTicketReply;

class SupportTicketReplyController extends Controller
{
    public function store(Request $request, int $ticketId): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        $user = $request->user();

        $isSupportStaff = $user->can('support.ticket.manage');

        $hasAccess = $isSupportStaff
            || (int) $ticket->submitted_by === (int) $user->id
            || (int) $ticket->assigned_to === (int) $user->id;

        abort_unless($hasAccess, 403, 'You cannot reply to this ticket.');

        abort_if(
            in_array($ticket->status, ['closed', 'resolved'], true),
            422,
            'This ticket is not accepting replies.'
        );

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['is_internal']) && !$isSupportStaff) {
            abort(403, 'Only support staff can create internal notes.');
        }

        $reply = DB::transaction(function () use (
            $ticket,
            $user,
            $validated,
            $isSupportStaff
        ) {
            $reply = SupportTicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => $isSupportStaff
                    && ($validated['is_internal'] ?? false),
            ]);

            if (!$reply->is_internal && $ticket->status === 'waiting_for_user') {
                $oldStatus = $ticket->status;
                $ticket->status = 'in_progress';
                $ticket->save();

                SupportTicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'changed_by' => $user->id,
                    'field_name' => 'status',
                    'old_value' => $oldStatus,
                    'new_value' => 'in_progress',
                    'note' => 'Reply received',
                ]);
            }

            return $reply;
        });

        return response()->json([
            'success' => true,
            'message' => 'Reply submitted successfully.',
            'data' => $reply->load('user:id,name'),
        ], 201);
    }
}