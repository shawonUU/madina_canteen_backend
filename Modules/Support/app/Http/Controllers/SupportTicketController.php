<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Models\SupportTicketHistory;

use Illuminate\Support\Str;
class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $canViewAll = $user->can('support.ticket.view-all');

        $query = SupportTicket::query()
            ->with([
                'submitter:id,name',
                'assignee:id,name',
            ])
            ->when(!$canViewAll, function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('submitted_by', $user->id)
                        ->orWhere('assigned_to', $user->id);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('priority'), function ($query) use ($request) {
                $query->where('priority', $request->priority);
            })
            ->when($request->filled('module_name'), function ($query) use ($request) {
                $query->where('module_name', $request->module_name);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->latest();

        $tickets = $query->paginate(
            min((int) $request->input('per_page', 15), 100)
        );

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'last_page' => $tickets->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'module_name' => ['nullable', 'string', 'max:100'],
            'issue_type' => [
                'required',
                Rule::in(SupportTicket::ISSUE_TYPES),
            ],
            'priority' => [
                'required',
                Rule::in(SupportTicket::PRIORITIES),
            ],
        ]);

        $ticket = DB::transaction(function () use ($request, $validated) {
            $ticket = SupportTicket::create([
                ...$validated,
                'ticket_number' => 'SUP-' . now()->format('YmdHis')
                    . '-' . strtoupper(\Illuminate\Support\Str::random(5)),
                'status' => 'open',
                'submitted_by' => $request->user()->id,
            ]);

            SupportTicketHistory::create([
                'ticket_id' => $ticket->id,
                'changed_by' => $request->user()->id,
                'field_name' => 'status',
                'old_value' => null,
                'new_value' => 'open',
                'note' => 'Ticket created',
            ]);

            return $ticket;
        });

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully.',
            'data' => $ticket->load('submitter:id,name'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::with([
            'submitter:id,name',
            'assignee:id,name',
            'replies.user:id,name',
            'replies.attachments',
            'attachments',
            'histories.changedBy:id,name',
        ])->findOrFail($id);

        $this->authorizeTicketAccess($request, $ticket);

        if ($ticket->replies->contains(
            fn ($reply) => $reply->is_internal
        ) && !$request->user()->can('support.ticket.view-all')) {
            $ticket->setRelation(
                'replies',
                $ticket->replies->where('is_internal', false)->values()
            );
        }

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $user = $request->user();

        if (!$user->can('support.ticket.manage')) {
            abort(403, 'You are not allowed to update this ticket.');
        }

        $validated = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'module_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'issue_type' => [
                'sometimes',
                'required',
                Rule::in(SupportTicket::ISSUE_TYPES),
            ],
            'priority' => [
                'sometimes',
                'required',
                Rule::in(SupportTicket::PRIORITIES),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(SupportTicket::STATUSES),
            ],
        ]);

        DB::transaction(function () use ($ticket, $validated, $user) {
            foreach ($validated as $field => $value) {
                $oldValue = $ticket->{$field};

                if ((string) $oldValue === (string) $value) {
                    continue;
                }

                $ticket->{$field} = $value;

                SupportTicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'changed_by' => $user->id,
                    'field_name' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $value,
                ]);
            }

            if (isset($validated['status'])) {
                if ($validated['status'] === 'resolved') {
                    $ticket->resolved_at = now();
                }

                if ($validated['status'] === 'closed') {
                    $ticket->closed_at = now();
                }

                if ($validated['status'] === 'reopened') {
                    $ticket->resolved_at = null;
                    $ticket->closed_at = null;
                }
            }

            $ticket->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully.',
            'data' => $ticket->fresh(),
        ]);
    }

    private function authorizeTicketAccess(
        Request $request,
        SupportTicket $ticket
    ): void {
        $user = $request->user();

        $hasAccess = $user->can('support.ticket.view-all')
            || (int) $ticket->submitted_by === (int) $user->id
            || (int) $ticket->assigned_to === (int) $user->id;

        abort_unless($hasAccess, 403, 'You cannot access this ticket.');
    }
}