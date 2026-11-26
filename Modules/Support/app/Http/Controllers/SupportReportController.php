<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Support\Services\SupportTicketQuery;

class SupportReportController extends Controller
{
    public function index(Request $request, SupportTicketQuery $tickets)
    {
        abort_unless($request->user()->can('support.report.view'), 403, 'You cannot view support reports.');
        $query = $tickets->build($request);
        if ($request->boolean('export')) {
            return response()->streamDownload(function () use ($query) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Ticket', 'Subject', 'Module', 'Issue Type', 'Requester', 'Assignee', 'Priority', 'Status', 'Created', 'Resolved', 'Closed']);
                foreach ((clone $query)->with(['submitter:id,name', 'assignee:id,name'])->orderBy('id')->lazy(500) as $ticket) {
                    $values = [$ticket->ticket_number, $ticket->subject, $ticket->module_name, $ticket->issue_type, $ticket->submitter?->name, $ticket->assignee?->name, $ticket->priority, $ticket->status, $ticket->created_at, $ticket->resolved_at, $ticket->closed_at];
                    fputcsv($output, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'" . $v : $v, $values));
                }
                fclose($output);
            }, 'support-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $statuses = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $priorities = (clone $query)->selectRaw('priority, COUNT(*) AS total')->groupBy('priority')->pluck('total', 'priority');
        $seconds = 0;
        $resolvedCount = 0;
        foreach ((clone $query)->whereNotNull('resolved_at')->select(['id', 'created_at', 'resolved_at'])->orderBy('id')->lazy(500) as $ticket) {
            $duration = $ticket->resolved_at->getTimestamp() - $ticket->created_at->getTimestamp();
            if ($duration >= 0) {
                $seconds += $duration;
                $resolvedCount++;
            }
        }
        return response()->json($tickets->paginate(clone $query, $request) + [
            'summary' => [
                'total' => (int) $statuses->sum(),
                'statuses' => $statuses,
                'priorities' => $priorities,
                'average_resolution_hours' => $resolvedCount ? round($seconds / $resolvedCount / 3600, 2) : null,
                'resolution_sample_count' => $resolvedCount,
            ],
        ]);
    }
}
