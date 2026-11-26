<?php

namespace Modules\Meeting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Meeting\Models\MeetingParticipant;
use Modules\Meeting\Services\MeetingQuery;

class MeetingReportController extends Controller
{
    public function summary(Request $request, MeetingQuery $meetings)
    {
        abort_unless($request->user()->can('meeting.report.view'), 403, 'You cannot view meeting reports.');
        $query = $meetings->build($request);
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $query = $meetings->sort($query, $request);
        if ($request->boolean('export')) {
            return $this->csv($query, ['Meeting Number', 'Title', 'Date', 'Start', 'End', 'Type', 'Location', 'Status', 'Approval', 'Participants'], fn ($meeting) => [$meeting->meeting_number, $meeting->title, $meeting->meeting_date?->format('Y-m-d'), $meeting->start_time, $meeting->end_time, $meeting->meeting_type, $meeting->location, $meeting->status, $meeting->approval_status, $meeting->participants_count], 'meeting-summary.csv');
        }
        $page = $query->paginate(max(1, min($request->integer('per_page', 25), 100)));
        return response()->json([
            'data' => $page->items(),
            'meta' => ['total' => $page->total(), 'last_page' => $page->lastPage(), 'current_page' => $page->currentPage()],
            'summary' => ['total' => (int) $counts->sum(), 'statuses' => $counts],
        ]);
    }

    public function attendance(Request $request, MeetingQuery $meetings)
    {
        abort_unless($request->user()->can('meeting.attendance.report'), 403, 'You cannot view attendance reports.');
        $meetingQuery = $meetings->build($request);
        $query = MeetingParticipant::query()->whereIn('meeting_id', $meetingQuery->select('meetings.id'));
        if ($request->filled('filter.participant')) {
            $name = '%' . $request->input('filter.participant') . '%';
            $query->where(fn ($q) => $q->where('guest_name', 'like', $name)->orWhereHas('employee', fn ($q) => $q->where('name', 'like', $name)));
        }
        foreach (['participation_type', 'invitation_status'] as $field) {
            if ($request->filled("filter.$field")) {
                $query->where($field, $request->input("filter.$field"));
            }
        }
        if ($request->filled('filter.attendance_status')) {
            if ($request->input('filter.attendance_status') === 'unmarked') {
                $query->whereDoesntHave('attendance');
            } else {
                $query->whereHas('attendance', fn ($q) => $q->where('status', $request->input('filter.attendance_status')));
            }
        }
        $counts = [];
        foreach (['present', 'absent', 'late', 'excused'] as $status) {
            $counts[$status] = (clone $query)->whereHas('attendance', fn ($q) => $q->where('status', $status))->count();
        }
        $counts['unmarked'] = (clone $query)->whereDoesntHave('attendance')->count();
        $query->with(['meeting:id,meeting_number,title,meeting_date', 'employee:id,name', 'attendance'])->orderByDesc('meeting_id')->orderBy('id');
        if ($request->boolean('export')) {
            return $this->csv($query, ['Meeting', 'Title', 'Date', 'Participant', 'Participation', 'Invitation', 'Attendance'], fn ($participant) => [$participant->meeting?->meeting_number, $participant->meeting?->title, $participant->meeting?->meeting_date?->format('Y-m-d'), $participant->employee?->name ?? $participant->guest_name, $participant->participation_type, $participant->invitation_status, $participant->attendance?->status ?? 'unmarked'], 'meeting-attendance.csv');
        }
        $page = $query->paginate(max(1, min($request->integer('per_page', 25), 100)));
        return response()->json([
            'data' => $page->items(),
            'meta' => ['total' => $page->total(), 'last_page' => $page->lastPage(), 'current_page' => $page->currentPage()],
            'summary' => ['total' => array_sum($counts), 'statuses' => $counts],
        ]);
    }

    private function csv($query, array $headers, callable $row, string $filename)
    {
        return response()->streamDownload(function () use ($query, $headers, $row) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            foreach ($query->lazy(500) as $record) {
                fputcsv($output, array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'" . $value : $value, $row($record)));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
