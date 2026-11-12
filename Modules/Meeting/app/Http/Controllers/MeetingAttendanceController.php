<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingAttendance;

class MeetingAttendanceController extends Controller
{
    public function index(Meeting $meeting): JsonResponse
    {
        return response()->json([
            'data' => $meeting->attendances()
                ->with('participant')
                ->get(),
        ]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'meeting_participant_id' => [
                'required',
                'integer',
                Rule::exists('meeting_participants', 'id')
                    ->where('meeting_id', $meeting->id),
            ],
            'status' => [
                'required',
                Rule::in(['present', 'absent', 'late', 'excused']),
            ],
            'remarks' => ['nullable', 'string'],
        ]);

        $attendance = MeetingAttendance::updateOrCreate(
            [
                'meeting_id' => $meeting->id,
                'meeting_participant_id' => $data['meeting_participant_id'],
            ],
            [
                'status' => $data['status'],
                'remarks' => $data['remarks'] ?? null,
                'checked_in_at' => in_array(
                    $data['status'],
                    ['present', 'late'],
                    true
                ) ? now() : null,
                'marked_by' => $request->user()->getAuthIdentifier(),
            ]
        );

        return response()->json([
            'message' => 'Attendance saved successfully.',
            'data' => $attendance,
        ]);
    }

    public function bulkUpdate(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.meeting_participant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('meeting_participants', 'id')
                    ->where('meeting_id', $meeting->id),
            ],
            'attendances.*.status' => [
                'required',
                Rule::in(['present', 'absent', 'late', 'excused']),
            ],
            'attendances.*.remarks' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $meeting, $request) {
            foreach ($data['attendances'] as $item) {
                MeetingAttendance::updateOrCreate(
                    [
                        'meeting_id' => $meeting->id,
                        'meeting_participant_id' =>
                            $item['meeting_participant_id'],
                    ],
                    [
                        'status' => $item['status'],
                        'remarks' => $item['remarks'] ?? null,
                        'checked_in_at' => in_array(
                            $item['status'],
                            ['present', 'late'],
                            true
                        ) ? now() : null,
                        'marked_by' => $request->user()->getAuthIdentifier(),
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Attendance updated successfully.',
        ]);
    }
}
