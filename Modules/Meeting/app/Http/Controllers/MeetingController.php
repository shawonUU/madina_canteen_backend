<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Services\MeetingAccess;
use Modules\Meeting\Services\MeetingQuery;

class MeetingController extends Controller
{
    public function index(Request $request, MeetingQuery $meetings): JsonResponse
    {
        $query = $meetings->build($request);
        if ($request->boolean('options')) {
            return response()->json(['data' => $query->select(['id', 'meeting_number', 'title', 'meeting_date', 'start_time', 'end_time', 'status', 'location', 'meeting_type', 'organizer_id', 'chairperson_id'])->orderByDesc('meeting_date')->get()]);
        }
        return response()->json($meetings->sort($query, $request)->paginate(max(1, min($request->integer('per_page', 25), 100))));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_type' => [
                'required',
                Rule::in(['internal', 'external', 'online', 'hybrid']),
            ],
            'organizer_id' => ['nullable', 'integer', 'exists:users,id'],
            'chairperson_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'meeting_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:2048'],
            'status' => [
                'sometimes',
                Rule::in(['draft', 'scheduled']),
            ],
        ]);

        $data['meeting_number'] = 'MTG-'
            . now()->format('YmdHis')
            . '-'
            . Str::upper(Str::random(4));

        $data['organizer_id'] = $data['organizer_id'] ?? $request->user()->getAuthIdentifier();
        abort_if((int) $data['organizer_id'] !== (int) $request->user()->id && !$request->user()->can('meeting.manage-all'), 403, 'You cannot create meetings for another organizer.');
        $data['status'] = $data['status'] ?? 'scheduled';

        $meeting = Meeting::create($data);

        return response()->json([
            'message' => 'Meeting created successfully.',
            'data' => $meeting,
        ], 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeView($request->user(), $meeting);
        $meeting->loadCount('participants');
        $meeting->load([
            'organizer:id,name',
            'chairperson:id,name',
            'participants.employee:id,name',
            'participants.attendance',
        ]);
        if ($request->user()->can('meeting.agenda.view')) $meeting->load('agendas');
        if ($request->user()->can('meeting.attendance.view')) $meeting->load('attendances');
        if ($request->user()->can('meeting.minutes.view')) $meeting->load('minutes');

        return response()->json([
            'data' => $meeting,
        ]);
    }

    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_type' => [
                'sometimes',
                Rule::in(['internal', 'external', 'online', 'hybrid']),
            ],
            'organizer_id' => ['nullable', 'integer', 'exists:users,id'],
            'chairperson_id' => ['nullable', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'meeting_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:2048'],
            'status' => [
                'sometimes',
                Rule::in([
                    'draft',
                    'scheduled',
                    'ongoing',
                    'in_progress',
                    'completed',
                    'cancelled',
                ]),
            ],
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        if (array_key_exists('organizer_id', $data)) {
            $data['organizer_id'] = $data['organizer_id'] ?? $meeting->organizer_id;
            abort_if((int) $data['organizer_id'] !== (int) $meeting->organizer_id && !$request->user()->can('meeting.manage-all'), 403, 'You cannot change the meeting organizer.');
        }
        $startTime = substr($data['start_time'] ?? $meeting->start_time, 0, 5);
        $endTime = substr($data['end_time'] ?? $meeting->end_time, 0, 5);

        if ($endTime <= $startTime) {
            return response()->json([
                'message' => 'End time must be after start time.',
                'errors' => [
                    'end_time' => [
                        'End time must be after start time.',
                    ],
                ],
            ], 422);
        }

        $meeting->update($data);

        return response()->json([
            'message' => 'Meeting updated successfully.',
            'data' => $meeting->fresh(),
        ]);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        $meeting->delete();

        return response()->json([
            'message' => 'Meeting deleted successfully.',
        ]);
    }
}
