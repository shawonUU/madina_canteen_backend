<?php

namespace Modules\Meeting\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Meeting\Models\Meeting;

class MeetingQuery
{
    public function build(Request $request): Builder
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'filter' => ['sometimes', 'array'],
            'filter.*' => ['nullable', 'string', 'max:255'],
        ]);
        $query = MeetingAccess::visibleTo(Meeting::query(), $request->user());
        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->input('search')) . '%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $search)->orWhere('meeting_number', 'like', $search)->orWhere('location', 'like', $search));
        }
        if ($request->filled('filter.meeting_number')) {
            $search = '%' . $request->input('filter.meeting_number') . '%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $search)->orWhere('meeting_number', 'like', $search)->orWhere('location', 'like', $search));
        }
        foreach (['title', 'meeting_type', 'location', 'status', 'approval_status', 'start_time', 'end_time'] as $column) {
            if ($request->filled("filter.$column")) {
                $query->where($column, 'like', '%' . $request->input("filter.$column") . '%');
            }
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('filter.meeting_date')) {
            $request->validate(['filter.meeting_date' => ['date_format:Y-m-d']]);
            $query->whereDate('meeting_date', $request->input('filter.meeting_date'));
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if ($request->filled($key)) {
                $query->whereDate('meeting_date', $operator, $request->input($key));
            }
        }
        if ($request->filled('filter.participants_count')) {
            $request->validate(['filter.participants_count' => ['integer', 'min:0']]);
            $query->has('participants', '=', (int) $request->input('filter.participants_count'));
        }
        return $query;
    }

    public function sort(Builder $query, Request $request): Builder
    {
        $allowed = ['id', 'meeting_number', 'title', 'meeting_type', 'meeting_date', 'start_time', 'end_time', 'status', 'approval_status', 'participants_count', 'location'];
        $column = $request->input('sort_by', $request->input('sort', 'meeting_date'));
        $column = in_array($column, $allowed, true) ? $column : 'meeting_date';
        $direction = $request->input('sort_direction', $request->input('direction')) === 'asc' ? 'asc' : 'desc';
        return $query->withCount('participants')->orderBy($column, $direction)->orderBy('id', 'desc');
    }
}
