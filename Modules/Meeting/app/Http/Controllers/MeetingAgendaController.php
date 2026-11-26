<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingAgenda;
use Modules\Meeting\Services\MeetingAccess;

class MeetingAgendaController extends Controller
{
    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeView($request->user(), $meeting);
        return response()->json([
            'data' => $meeting->agendas()->get(),
        ]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'presenter_id' => ['nullable', 'integer', 'exists:users,id'],
            'presenter' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $agenda = $meeting->agendas()->create($data);

        return response()->json([
            'message' => 'Agenda created successfully.',
            'data' => $agenda,
        ], 201);
    }

    public function show(
        Request $request,
        Meeting $meeting,
        MeetingAgenda $agenda
    ): JsonResponse {
        MeetingAccess::authorizeView($request->user(), $meeting);
        abort_unless((int) $agenda->meeting_id === (int) $meeting->id, 404);

        return response()->json(['data' => $agenda]);
    }

    public function update(
        Request $request,
        Meeting $meeting,
        MeetingAgenda $agenda
    ): JsonResponse {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        abort_unless((int) $agenda->meeting_id === (int) $meeting->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'presenter_id' => ['nullable', 'integer', 'exists:users,id'],
            'presenter' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $agenda->update($data);

        return response()->json([
            'message' => 'Agenda updated successfully.',
            'data' => $agenda->fresh(),
        ]);
    }

    public function destroy(
        Request $request,
        Meeting $meeting,
        MeetingAgenda $agenda
    ): JsonResponse {
        MeetingAccess::authorizeManage($request->user(), $meeting);
        abort_unless((int) $agenda->meeting_id === (int) $meeting->id, 404);

        $agenda->delete();

        return response()->json([
            'message' => 'Agenda deleted successfully.',
        ]);
    }
}
