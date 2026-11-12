<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Modules\Meeting\Models\Meeting;

class MeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $meetings = Meeting::query()
            ->withCount('participants')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('meeting_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('meeting_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('meeting_date', '<=', $request->date_to);
            })
            ->orderByDesc('meeting_date')
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 15));

        return response()->json($meetings);
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
            'chairperson_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
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

        $data['organizer_id'] = $request->user()->getAuthIdentifier();
        $data['status'] = $data['status'] ?? 'scheduled';

        $meeting = Meeting::create($data);

        return response()->json([
            'message' => 'Meeting created successfully.',
            'data' => $meeting,
        ], 201);
    }

    public function show(Meeting $meeting): JsonResponse
    {
        $meeting->load([
            'participants',
            'agendas',
            'attendances',
            'minutes',
        ]);

        return response()->json([
            'data' => $meeting,
        ]);
    }

    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_type' => [
                'sometimes',
                Rule::in(['internal', 'external', 'online', 'hybrid']),
            ],
            'chairperson_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
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
                    'completed',
                    'cancelled',
                ]),
            ],
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        $startTime = $data['start_time'] ?? $meeting->start_time;
        $endTime = $data['end_time'] ?? $meeting->end_time;

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

    public function destroy(Meeting $meeting): JsonResponse
    {
        $meeting->delete();

        return response()->json([
            'message' => 'Meeting deleted successfully.',
        ]);
    }
}
