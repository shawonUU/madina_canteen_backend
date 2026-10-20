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
                'invitation_status' => 'pending',
            ]);
        } else {
            $participant = $meeting->participants()->create([
                'employee_id' => null,
                'guest_name' => $validated['guest_name'],
                'guest_company' => $validated['guest_company'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
                'guest_phone' => $validated['guest_phone'] ?? null,
                'participation_type' => $validated['participation_type'],
                'invitation_status' => 'pending',
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

        $invitations = MeetingParticipant::query()
            ->with([
                'meeting',
            ])
            ->where('employee_id', $employeeId)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $invitations,
        ]);
    }

    public function updateInvitationStatus(
        Request $request,
        int $meetingId,
        int $participantId
    ): JsonResponse {
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

        if (
            strtolower($participant->invitation_status ?? '') !== 'pending'
        ) {
            return response()->json([
                'message' => 'Only pending invitations can be updated.',
            ], 422);
        }

        DB::transaction(function () use ($participant, $validated) {
            $participant->invitation_status =
                $validated['invitation_status'];

            $participant->save();
        });

        return response()->json([
            'message' => 'Invitation status updated successfully.',
            'data' => $participant->fresh()->load('meeting'),
        ]);
    }
}
