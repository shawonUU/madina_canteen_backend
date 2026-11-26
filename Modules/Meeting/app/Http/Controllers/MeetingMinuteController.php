<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingMinute;
use Modules\Meeting\Services\MeetingAccess;
use Illuminate\Support\Facades\DB;

class MeetingMinuteController extends Controller
{
    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeView($request->user(), $meeting);
        return response()->json([
            'data' => $meeting->minutes,
        ]);
    }

    public function upsert(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'discussion_summary' => ['nullable', 'string'],
            'decisions' => ['nullable', 'string'],
        ]);

        $minute = DB::transaction(function () use ($meeting, $data, $request) {
            Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            return MeetingMinute::updateOrCreate(
            ['meeting_id' => $meeting->id],
            [
                ...$data,
                'prepared_by' => $request->user()->getAuthIdentifier(),
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
            ]
        );
        });

        return response()->json([
            'message' => 'Meeting minutes saved successfully.',
            'data' => $minute,
        ]);
    }

    public function approve(
        Request $request,
        Meeting $meeting
    ): JsonResponse {
        MeetingAccess::authorizeView($request->user(), $meeting);
        abort_unless($request->user()->can('meeting.minutes.approve'), 403, 'You cannot approve meeting minutes.');
        $minute = DB::transaction(function () use ($meeting, $request) {
            Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            $minute = MeetingMinute::where('meeting_id', $meeting->id)->first();
            abort_unless($minute, 404, 'Meeting minutes have not been prepared.');
            if ($minute->status !== 'approved') {
                $minute->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->getAuthIdentifier(),
                    'approved_at' => now(),
                ]);
            }
            return $minute;
        });

        return response()->json([
            'message' => 'Meeting minutes approved successfully.',
            'data' => $minute->fresh(),
        ]);
    }
}
