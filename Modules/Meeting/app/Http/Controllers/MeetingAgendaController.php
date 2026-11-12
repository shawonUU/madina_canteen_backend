<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingAgenda;

class MeetingAgendaController extends Controller
{
    public function index(Meeting $meeting): JsonResponse
    {
        return response()->json([
            'data' => $meeting->agendas()->get(),
        ]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'presenter_id' => ['nullable', 'integer'],
        ]);

        $agenda = $meeting->agendas()->create($data);

        return response()->json([
            'message' => 'Agenda created successfully.',
            'data' => $agenda,
        ], 201);
    }

    public function show(
        Meeting $meeting,
        MeetingAgenda $agenda
    ): JsonResponse {
        abort_unless($agenda->meeting_id === $meeting->id, 404);

        return response()->json(['data' => $agenda]);
    }

    public function update(
        Request $request,
        Meeting $meeting,
        MeetingAgenda $agenda
    ): JsonResponse {
        abort_unless($agenda->meeting_id === $meeting->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'presenter_id' => ['nullable', 'integer'],
        ]);

        $agenda->update($data);

        return response()->json([
            'message' => 'Agenda updated successfully.',
            'data' => $agenda->fresh(),
        ]);
    }

    public function destroy(
        Meeting $meeting,
        MeetingAgenda $agenda
    ): JsonResponse {
        abort_unless($agenda->meeting_id === $meeting->id, 404);

        $agenda->delete();

        return response()->json([
            'message' => 'Agenda deleted successfully.',
        ]);
    }
}
