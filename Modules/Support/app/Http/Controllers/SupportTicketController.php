<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Models\SupportTicketHistory;
use Illuminate\Support\Facades\Storage;
use Modules\Support\Models\SupportTicketAttachment;

use Illuminate\Support\Str;
use Modules\Support\Services\SupportAccess;
use Modules\Support\Services\SupportTicketQuery;
class SupportTicketController extends Controller
{
    public function index(Request $request, SupportTicketQuery $tickets): JsonResponse
    {
        return response()->json($tickets->paginate($tickets->build($request), $request));
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
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $uploadedPaths = [];

        try {
            $ticket = DB::transaction(function () use (
                $request,
                $validated,
                &$uploadedPaths
            ) {
                $ticket = SupportTicket::create([
                    'subject' => $validated['subject'],
                    'description' => $validated['description'],
                    'module_name' => $validated['module_name'] ?? null,
                    'issue_type' => $validated['issue_type'],
                    'priority' => $validated['priority'],
                    'ticket_number' => 'SUP-' . now()->format('YmdHis')
                        . '-' . strtoupper(Str::random(5)),
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

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store(
                        'support-tickets/' . $ticket->id,
                        'public'
                    );

                    $uploadedPaths[] = $path;

                    SupportTicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'reply_id' => null,
                        'uploaded_by' => $request->user()->id,
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }

                return $ticket;
            });

            return response()->json([
                'success' => true,
                'message' => 'Support ticket created successfully.',
                'data' => $ticket->load([
                    'submitter:id,name',
                    'attachments',
                ]),
            ], 201);

        } catch (\Throwable $e) {
            if (!empty($uploadedPaths)) {
                Storage::disk('public')->delete($uploadedPaths);
            }

            throw $e;
        }
    }


    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::with([
            'submitter:id,name',
            'assignee:id,name',
            'replies.user:id,name',
            'replies.attachments',
            'attachments' => fn ($query) => $query->whereNull('reply_id'),
            'histories.changedBy:id,name',
        ])->findOrFail($id);

        $this->authorizeTicketAccess($request, $ticket);

        if ($ticket->replies->contains(
            fn ($reply) => $reply->is_internal
        ) && !$request->user()->can('support.ticket.internal')) {
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
        $user = $request->user();
        $uploadedPaths = [];

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
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        try {
            $ticket = DB::transaction(function () use (
                $id,
                $user,
                $validated,
                $request,
                &$uploadedPaths
            ) {
                $ticket = SupportTicket::whereKey($id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $isOwner = (int) $ticket->submitted_by === (int) $user->id;
                $canManage = $user->can('support.ticket.manage') && SupportAccess::canView($user, $ticket);

                abort_unless(
                    $isOwner || $canManage,
                    403,
                    'You are not allowed to update this ticket.'
                );

                abort_unless(
                    $ticket->status === 'open',
                    422,
                    'Only open tickets can be updated. This ticket is no longer editable.'
                );

                foreach ([
                    'subject',
                    'description',
                    'module_name',
                    'issue_type',
                    'priority',
                ] as $field) {
                    if (!array_key_exists($field, $validated)) {
                        continue;
                    }

                    $oldValue = $ticket->{$field};
                    $newValue = $validated[$field];

                    if ((string) $oldValue === (string) $newValue) {
                        continue;
                    }

                    $ticket->{$field} = $newValue;

                    SupportTicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'changed_by' => $user->id,
                        'field_name' => $field,
                        'old_value' => $oldValue,
                        'new_value' => $newValue,
                        'note' => 'Ticket updated by requester',
                    ]);
                }

                $ticket->save();

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store(
                        'support-tickets/' . $ticket->id,
                        'public'
                    );

                    $uploadedPaths[] = $path;

                    SupportTicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'reply_id' => null,
                        'uploaded_by' => $user->id,
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }

                return $ticket;
            });

            return response()->json([
                'success' => true,
                'message' => 'Support ticket updated successfully.',
                'data' => $ticket->fresh()->load([
                    'submitter:id,name',
                    'assignee:id,name',
                    'attachments',
                ]),
            ]);

        } catch (\Throwable $e) {
            if (!empty($uploadedPaths)) {
                Storage::disk('public')->delete($uploadedPaths);
            }

            throw $e;
        }
    }

    private function authorizeTicketAccess(
        Request $request,
        SupportTicket $ticket
    ): void {
        SupportAccess::authorize($request->user(), $ticket);
    }
}
