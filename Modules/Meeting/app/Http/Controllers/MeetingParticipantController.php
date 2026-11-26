<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingParticipant;
use Modules\Meeting\Services\MeetingAccess;
use Modules\Meeting\Services\MeetingAttendanceService;

class MeetingParticipantController extends Controller
{
    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeView($request->user(), $meeting);
        return response()->json(['data' => $meeting->participants()->with(['employee:id,name,code', 'attendance'])->orderBy('id')->get()]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'participant_type' => ['required', Rule::in(['employee', 'guest'])],
            'employee_id' => ['required_if:participant_type,employee', 'nullable', 'integer', Rule::exists('employees', 'id')->where('status', 'Active')],
            'guest_name' => ['required_if:participant_type,guest', 'nullable', 'string', 'max:255'],
            'guest_company' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            'participation_type' => ['required', Rule::in(['required', 'optional'])],
        ]);
        $participant = DB::transaction(function () use ($meeting, $data) {
            Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $employee = $data['participant_type'] === 'employee';
            if ($employee) {
                abort_if($meeting->participants()->where('employee_id', $data['employee_id'])->exists(), 422, 'This employee is already a participant.');
            }
            return $meeting->participants()->create([
                'employee_id' => $employee ? $data['employee_id'] : null,
                'guest_name' => $employee ? null : $data['guest_name'],
                'guest_company' => $employee ? null : ($data['guest_company'] ?? null),
                'guest_email' => $employee ? null : ($data['guest_email'] ?? null),
                'guest_phone' => $employee ? null : ($data['guest_phone'] ?? null),
                'participation_type' => $data['participation_type'],
                'invitation_status' => 'Pending',
            ]);
        });
        return response()->json(['message' => 'Participant added successfully.', 'data' => $participant->load(['employee:id,name,code', 'attendance'])], 201);
    }

    public function show(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        MeetingAccess::authorizeView($request->user(), $meeting);
        abort_unless((int) $participant->meeting_id === (int) $meeting->id, 404);
        return response()->json(['data' => $participant->load(['employee:id,name,code', 'attendance'])]);
    }

    public function update(Request $request, Meeting $meeting, MeetingParticipant $participant, MeetingAttendanceService $attendance): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        abort_unless((int) $participant->meeting_id === (int) $meeting->id, 404);
        $data = $request->validate([
            'participation_type' => ['sometimes', Rule::in(['required', 'optional'])],
            'guest_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guest_company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guest_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'guest_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'invitation_status' => ['sometimes', Rule::in(['Pending', 'Accepted', 'Declined'])],
            'attendance_status' => ['sometimes', Rule::in(['present', 'absent', 'late', 'excused'])],
        ]);
        DB::transaction(function () use ($participant, $meeting, $data, $attendance, $request) {
            Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $participant = $meeting->participants()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
            if (isset($data['attendance_status'])) {
                $attendance->save($meeting, $participant->id, $data['attendance_status'], null, $request->user()->id);
                unset($data['attendance_status']);
            }
            if (isset($data['invitation_status']) && $data['invitation_status'] !== $participant->invitation_status) {
                $data['responded_at'] = $data['invitation_status'] === 'Pending' ? null : now();
            }
            $participant->update($data);
        });
        return response()->json(['message' => 'Participant updated successfully.', 'data' => $participant->fresh()->load(['employee:id,name,code', 'attendance'])]);
    }

    public function respond(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        abort_unless((int) $participant->meeting_id === (int) $meeting->id, 404);
        return $this->updateInvitationStatus($request, $meeting->id, $participant->id);
    }

    public function destroy(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        abort_unless((int) $participant->meeting_id === (int) $meeting->id, 404);
        DB::transaction(function () use ($participant) {
            $participant->attendance?->delete();
            $participant->delete();
        });
        return response()->json(['message' => 'Participant removed successfully.']);
    }

    public function myInvitations(Request $request): JsonResponse
    {
        $employeeId = $request->user()->employee_id;
        abort_unless($employeeId, 403, 'No employee profile is associated with your account.');
        $base = MeetingParticipant::query()->where('employee_id', $employeeId)->whereHas('meeting');
        $counts = (clone $base)->selectRaw('invitation_status, COUNT(*) AS total')->groupBy('invitation_status')->pluck('total', 'invitation_status');
        $query = $base->with(['meeting', 'attendance']);
        if ($request->filled('search')) {
            $value = '%' . trim((string) $request->input('search')) . '%';
            $query->whereHas('meeting', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $value)->orWhere('meeting_number', 'like', $value)));
        }
        foreach (['invitation_status', 'participation_type'] as $field) {
            if ($request->filled("filter.$field")) {
                $query->where($field, $request->input("filter.$field"));
            }
        }
        if ($request->filled('filter.meeting')) {
            $value = '%' . $request->input('filter.meeting') . '%';
            $query->whereHas('meeting', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $value)->orWhere('meeting_number', 'like', $value)));
        }
        if ($request->filled('filter.meeting_date')) {
            $request->validate(['filter.meeting_date' => ['date_format:Y-m-d']]);
            $query->whereHas('meeting', fn ($q) => $q->whereDate('meeting_date', $request->input('filter.meeting_date')));
        }
        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['meeting', 'meeting_date'], true)) {
            $query->orderBy(Meeting::select($sort === 'meeting' ? 'title' : 'meeting_date')->whereColumn('meetings.id', 'meeting_participants.meeting_id'), $direction);
        } else {
            $query->orderBy(in_array($sort, ['id', 'invitation_status', 'participation_type', 'created_at'], true) ? $sort : 'id', $direction);
        }
        $page = $query->paginate(max(1, min($request->integer('per_page', 25), 100)));
        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
            'counts' => ['total' => (int) $counts->sum(), 'Pending' => (int) ($counts['Pending'] ?? 0), 'Accepted' => (int) ($counts['Accepted'] ?? 0), 'Declined' => (int) ($counts['Declined'] ?? 0)],
        ]);
    }

    public function updateInvitationStatus(Request $request, int $meetingId, int $participantId): JsonResponse
    {
        $request->merge(['invitation_status' => ucfirst(strtolower((string) $request->input('invitation_status')))]);
        $data = $request->validate(['invitation_status' => ['required', 'in:Accepted,Declined'], 'response_note' => ['nullable', 'string', 'max:4000']]);
        abort_unless($request->user()->employee_id, 403, 'No employee profile is associated with your account.');
        $participant = DB::transaction(function () use ($request, $meetingId, $participantId, $data) {
            $participant = MeetingParticipant::whereKey($participantId)->where('meeting_id', $meetingId)->where('employee_id', $request->user()->employee_id)->whereHas('meeting')->lockForUpdate()->firstOrFail();
            abort_unless($participant->invitation_status === 'Pending', 422, 'Only pending invitations can be updated.');
            $participant->update($data + ['responded_at' => now()]);
            return $participant;
        });
        return response()->json(['message' => 'Invitation response saved.', 'data' => $participant->load(['meeting', 'attendance'])]);
    }
}
