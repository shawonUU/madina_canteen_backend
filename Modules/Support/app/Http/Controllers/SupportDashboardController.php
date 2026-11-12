<?php

namespace Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Support\Models\SupportTicket;

class SupportDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SupportTicket::query();

        if (!$user->can('support.ticket.view-all')) {
            $query->where(function ($builder) use ($user) {
                $builder->where('submitted_by', $user->id)
                    ->orWhere('assigned_to', $user->id);
            });
        }

        $total = (clone $query)->count();

        $open = (clone $query)
            ->whereIn('status', ['open', 'assigned', 'reopened'])
            ->count();

        $inProgress = (clone $query)
            ->where('status', 'in_progress')
            ->count();

        $waitingForUser = (clone $query)
            ->where('status', 'waiting_for_user')
            ->count();

        $resolved = (clone $query)
            ->whereIn('status', ['resolved', 'closed'])
            ->count();

        $critical = (clone $query)
            ->where('priority', 'critical')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();

        $recentTickets = (clone $query)
            ->with(['submitter:id,name', 'assignee:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        $statusSummary = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'data' => [
                'total_tickets' => $total,
                'open_tickets' => $open,
                'in_progress_tickets' => $inProgress,
                'waiting_for_user' => $waitingForUser,
                'resolved_tickets' => $resolved,
                'critical_tickets' => $critical,
                'status_summary' => $statusSummary,
                'recent_tickets' => $recentTickets,
            ],
        ]);
    }
}