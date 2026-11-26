<?php

namespace Modules\Support\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Support\Models\SupportTicket;

class SupportTicketQuery
{
    public function build(Request $request): Builder
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'scope' => ['nullable', 'in:mine,assigned,all'],
            'filter' => ['sometimes', 'array'],
            'filter.*' => ['nullable', 'string', 'max:255'],
        ]);
        $query = SupportAccess::visibleTo(SupportTicket::query(), $request->user());
        if ($request->input('scope') === 'mine') {
            $query->where('submitted_by', $request->user()->id);
        } elseif ($request->input('scope') === 'assigned') {
            $query->where('assigned_to', $request->user()->id);
        }
        foreach (['status', 'priority', 'module_name'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }
        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(fn (Builder $q) => $q->where('ticket_number', 'like', $search)->orWhere('subject', 'like', $search));
        }
        foreach (['ticket_number', 'module_name', 'issue_type', 'priority', 'status'] as $column) {
            if ($request->filled("filter.$column")) {
                $query->where($column, 'like', '%' . $request->input("filter.$column") . '%');
            }
        }
        if ($request->filled('filter.subject')) {
            $search = '%' . $request->input('filter.subject') . '%';
            $query->where(fn (Builder $q) => $q->where('subject', 'like', $search)->orWhere('issue_type', 'like', $search)->orWhere('module_name', 'like', $search));
        }
        foreach (['requester' => 'submitter', 'assignee' => 'assignee'] as $column => $relation) {
            if ($request->filled("filter.$column")) {
                if ($column === 'assignee' && strtolower(trim($request->input("filter.$column"))) === 'unassigned') {
                    $query->whereNull('assigned_to');
                    continue;
                }
                $search = '%' . $request->input("filter.$column") . '%';
                $query->whereHas($relation, fn (Builder $q) => $q->where('name', 'like', $search));
            }
        }
        foreach (['created_at', 'resolved_at', 'closed_at'] as $column) {
            if ($request->filled("filter.$column")) {
                $request->validate(["filter.$column" => ['date_format:Y-m-d']]);
                $query->whereDate($column, $request->input("filter.$column"));
            }
        }
        $dateColumn = $request->input('date_basis') === 'resolved' ? 'resolved_at' : 'created_at';
        if ($request->filled('date_from')) {
            $query->whereDate($dateColumn, '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate($dateColumn, '<=', $request->input('date_to'));
        }
        return $query;
    }

    public function paginate(Builder $query, Request $request): array
    {
        $allowed = ['id', 'ticket_number', 'subject', 'priority', 'status', 'created_at', 'resolved_at', 'closed_at'];
        $sort = in_array($request->input('sort'), $allowed, true) ? $request->input('sort') : 'id';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = $query->with(['submitter:id,name', 'assignee:id,name'])
            ->orderBy($sort, $direction)->orderBy('id', $direction)
            ->paginate(max(1, min($request->integer('per_page', 25), 100)));
        return [
            'success' => true,
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ];
    }
}
