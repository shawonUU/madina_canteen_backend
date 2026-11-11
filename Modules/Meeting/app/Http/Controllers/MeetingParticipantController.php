<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingParticipant;

use Illuminate\Support\Facades\DB;
class MeetingParticipantController extends Controller
{
    public function index(Meeting $meeting): JsonResponse
    {
        return response()->json([
            'data' => $meeting->participants()->with('employee')->paginate(20),
        ]);
    }

    public function store(
        Request $request,
        int $meetingId
    ): JsonResponse {
        $meeting = Meeting::findOrFail($meetingId);

        $validated = $request->validate([
            'participant_type' => [
                'required',
                Rule::in(['employee', 'guest']),
            ],
            'employee_id' => [
                'required_if:participant_type,employee',
                'nullable',
                'integer',
                'exists:employees,id',
            ],
            'guest_name' => [
                'required_if:participant_type,guest',
                'nullable',
                'string',
                'max:255',
            ],
            'guest_company' => [
                'nullable',
                'string',
                'max:255',
            ],
            'guest_email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'guest_phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'participation_type' => [
                'required',
                Rule::in(['required', 'optional']),
            ],
        ]);

        if ($validated['participant_type'] === 'employee') {
            $exists = $meeting->participants()
                ->where('employee_id', $validated['employee_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'This employee is already a participant.',
                ], 422);
            }

            $participant = $meeting->participants()->create([
                'employee_id' => $validated['employee_id'],
                'guest_name' => null,
                'guest_company' => null,
                'guest_email' => null,
                'guest_phone' => null,
                'participation_type' => $validated['participation_type'],
                'invitation_status' => 'Pending',
            ]);
        } else {
            $participant = $meeting->participants()->create([
                'employee_id' => null,
                'guest_name' => $validated['guest_name'],
                'guest_company' => $validated['guest_company'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
                'guest_phone' => $validated['guest_phone'] ?? null,
                'participation_type' => $validated['participation_type'],
                'invitation_status' => 'Pending',
            ]);
        }

        return response()->json([
            'message' => 'Participant added successfully.',
            'data' => $participant->load('employee'),
        ], 201);
    }

    public function show(
        Meeting $meeting,
        MeetingParticipant $participant
    ): JsonResponse {
        abort_unless(
            $participant->meeting_id === $meeting->id,
            404
        );

        return response()->json(['data' => $participant]);
    }

    public function update(
        Request $request,
        Meeting $meeting,
        MeetingParticipant $participant
    ): JsonResponse {
        abort_unless(
            $participant->meeting_id === $meeting->id,
            404
        );

        $data = $request->validate([
            'participation_type' => [
                'sometimes',
                Rule::in(['required', 'optional']),
            ],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
        ]);

        $participant->update($data);

        return response()->json([
            'message' => 'Participant updated successfully.',
            'data' => $participant->fresh(),
        ]);
    }

    public function respond(
        Request $request,
        Meeting $meeting,
        MeetingParticipant $participant
    ): JsonResponse {
        abort_unless(
            $participant->meeting_id === $meeting->id,
            404
        );

        $data = $request->validate([
            'invitation_status' => [
                'required',
                Rule::in(['accepted', 'declined']),
            ],
            'response_note' => ['nullable', 'string'],
        ]);

        $participant->update([
            ...$data,
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Invitation response saved.',
            'data' => $participant->fresh(),
        ]);
    }

    public function destroy(
        Meeting $meeting,
        MeetingParticipant $participant
    ): JsonResponse {
        abort_unless(
            $participant->meeting_id === $meeting->id,
            404
        );

        $participant->delete();

        return response()->json([
            'message' => 'Participant removed successfully.',
        ]);
    }

    public function myInvitations(Request $request): JsonResponse
{
    $user = $request->user();

    $employeeId = $user->employee_id
        ?? $user->employee?->id;

    if (!$employeeId) {
        return response()->json([
            'message' => 'No employee profile is associated with your account.',
            'data' => [],
        ], 403);
    }

    $query = MeetingParticipant::query()
        ->with('meeting')
        ->where('employee_id', $employeeId);

    // Global search
    if ($search = trim((string) $request->query('search', ''))) {
        $query->whereHas('meeting', function ($meetingQuery) use ($search) {
            $meetingQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('meeting_code', 'like', "%{$search}%");
            });
        });
    }

    // Invitation status filter
    if ($status = $request->input('filter.invitation_status')) {
        $query->where('invitation_status', $status);
    }

    // Participation type filter
    if ($type = $request->input('filter.participation_type')) {
        $query->where('participation_type', $type);
    }

    // Meeting title filter
    if ($meeting = $request->input('filter.meeting')) {
        $query->whereHas('meeting', function ($q) use ($meeting) {
            $q->where('title', 'like', "%{$meeting}%");
        });
    }

    // Meeting date filter
    if ($date = $request->input('filter.meeting_date')) {
        $query->whereHas('meeting', function ($q) use ($date) {
            $q->whereDate('meeting_date', $date);
        });
    }

    // Status counts for the current employee
    $statusCounts = MeetingParticipant::query()
        ->where('employee_id', $employeeId)
        ->selectRaw('invitation_status, COUNT(*) as total')
        ->groupBy('invitation_status')
        ->pluck('total', 'invitation_status');

    // Safe sorting
    $allowedSortColumns = [
        'id',
        'invitation_status',
        'participation_type',
        'created_at',
    ];

    $sort = (string) $request->query('sort', 'id');
    $direction = strtolower((string) $request->query('direction', 'desc'));

    if (!in_array($sort, $allowedSortColumns, true)) {
        $sort = 'id';
    }

    if (!in_array($direction, ['asc', 'desc'], true)) {
        $direction = 'desc';
    }

    $query->orderBy($sort, $direction);

    // Server-side pagination
    $perPage = min(
        max((int) $request->query('per_page', 10), 1),
        100
    );

    $invitations = $query->paginate($perPage)->withQueryString();

    return response()->json([
        'data' => $invitations->items(),
        'meta' => [
            'current_page' => $invitations->currentPage(),
            'per_page' => $invitations->perPage(),
            'total' => $invitations->total(),
            'last_page' => $invitations->lastPage(),
        ],
        'counts' => [
            'total' => (int) $statusCounts->sum(),
            'Pending' => (int) ($statusCounts['Pending'] ?? 0),
            'Accepted' => (int) ($statusCounts['Accepted'] ?? 0),
            'Declined' => (int) ($statusCounts['Declined'] ?? 0),
        ],
    ]);
}


public function updateInvitationStatus( Request $request, int $meetingId, int $participantId ): JsonResponse {
    $validated = $request->validate([
        'invitation_status' => [
            'required',
            'string',
            'in:Accepted,Declined',
        ],
    ]);

    $user = $request->user();

    $employeeId = $user->employee_id
        ?? $user->employee?->id;

    if (!$employeeId) {
        return response()->json([
            'message' => 'No employee profile is associated with your account.',
        ], 403);
    }

    $participant = MeetingParticipant::query()
        ->where('id', $participantId)
        ->where('meeting_id', $meetingId)
        ->where('employee_id', $employeeId)
        ->first();

    if (!$participant) {
        return response()->json([
            'message' => 'Invitation not found or you are not authorized to update it.',
        ], 404);
    }

    if ($participant->invitation_status !== 'Pending') {
        return response()->json([
            'message' => 'Only pending invitations can be updated.',
            'current_status' => $participant->invitation_status,
        ], 422);
    }

    try {
        $participant->invitation_status = $validated['invitation_status'];
        $participant->saveOrFail();

        $participant->refresh();

        return response()->json([
            'message' => 'Invitation status updated successfully.',
            'data' => $participant->load('meeting'),
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'message' => 'Failed to update invitation status.',
        ], 500);
    }
}

}
