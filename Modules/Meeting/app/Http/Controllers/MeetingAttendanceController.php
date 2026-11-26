<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Services\MeetingAccess;
use Modules\Meeting\Services\MeetingAttendanceService;

class MeetingAttendanceController extends Controller
{
    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeView($request->user(), $meeting);
        return response()->json(['data' => $meeting->attendances()->with('participant.employee:id,name,code')->get()]);
    }

    public function store(Request $request, Meeting $meeting, MeetingAttendanceService $attendance): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'meeting_participant_id' => ['required', 'integer', Rule::exists('meeting_participants', 'id')->where('meeting_id', $meeting->id)],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'remarks' => ['nullable', 'string', 'max:4000'],
        ]);
        $record = DB::transaction(function () use ($meeting, $attendance, $request, $data) {
            Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            return $attendance->save($meeting, $data['meeting_participant_id'], $data['status'], $data['remarks'] ?? null, $request->user()->id);
        });
        return response()->json(['message' => 'Attendance saved successfully.', 'data' => $record]);
    }

    public function bulkUpdate(Request $request, Meeting $meeting, MeetingAttendanceService $attendance): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'attendances' => ['required', 'array', 'min:1', 'max:1000'],
            'attendances.*.meeting_participant_id' => ['required', 'integer', 'distinct', Rule::exists('meeting_participants', 'id')->where('meeting_id', $meeting->id)],
            'attendances.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'attendances.*.remarks' => ['nullable', 'string', 'max:4000'],
        ]);
        DB::transaction(function () use ($data, $meeting, $request, $attendance) {
            Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            foreach ($data['attendances'] as $item) {
                $attendance->save($meeting, $item['meeting_participant_id'], $item['status'], $item['remarks'] ?? null, $request->user()->id);
            }
        });
        return response()->json(['message' => 'Attendance updated successfully.']);
    }
}
