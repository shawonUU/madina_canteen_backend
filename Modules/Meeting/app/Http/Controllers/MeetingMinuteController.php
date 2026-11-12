<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingMinute;

class MeetingMinuteController extends Controller
{
    public function show(Meeting $meeting): JsonResponse
    {
        return response()->json([
            'data' => $meeting->minutes,
        ]);
    }

    public function upsert(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'discussion_summary' => ['nullable', 'string'],
            'decisions' => ['nullable', 'string'],
        ]);

        $minute = MeetingMinute::updateOrCreate(
            ['meeting_id' => $meeting->id],
            [
                ...$data,
                'prepared_by' => $request->user()->getAuthIdentifier(),
                'status' => 'draft',
            ]
        );

        return response()->json([
            'message' => 'Meeting minutes saved successfully.',
            'data' => $minute,
        ]);
    }

    public function approve(
        Request $request,
        Meeting $meeting
    ): JsonResponse {
        $minute = $meeting->minutes;

        if (!$minute) {
            return response()->json([
                'message' => 'Meeting minutes have not been prepared.',
            ], 404);
        }

        if ($minute->status === 'approved') {
            return response()->json([
                'message' => 'Meeting minutes are already approved.',
                'data' => $minute,
            ]);
        }

        $minute->update([
            'status' => 'approved',
            'approved_by' => $request->user()->getAuthIdentifier(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Meeting minutes approved successfully.',
            'data' => $minute->fresh(),
        ]);
    }
}
