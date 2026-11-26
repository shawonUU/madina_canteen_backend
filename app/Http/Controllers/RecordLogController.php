<?php

namespace App\Http\Controllers;

use App\Models\RecordLog;
use App\Services\AccessService;
use App\Services\RecordLogAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecordLogController extends Controller
{
    public function show(Request $request, string $resource, string $record, RecordLogAccess $access)
    {
        $model = $access->resolve($resource, $record);
        $access->authorize($request->user(), $resource, $model);
        $resources = [$resource];
        foreach (config('audit.resources') as $childResource => $meta) {
            if (($meta[2] ?? null) === $resource && ($access->mayRead($request->user(), $childResource) || $resource === 'approval_requests')) $resources[] = $childResource;
        }
        $query = RecordLog::where(function ($query) use ($resource, $record) {
            $query->where(fn ($q) => $q->where('resource', $resource)->where('record_id', $record))
                ->orWhere(fn ($q) => $q->where('parent_resource', $resource)->where('parent_id', $record));
        });
        $query->whereIn('resource', $resources);
        if (!$request->user()->can('support.ticket.internal')) $query->where('is_private', false);
        return response()->json(['data' => $query->orderByDesc('id')->paginate(20)]);
    }

    public function index(Request $request, AccessService $access)
    {
        $access->authorize($request->user(), 'audit.view-all');
        $data = $request->validate([
            'resource' => ['nullable', Rule::in(array_keys(config('audit.resources')))],
            'record_id' => ['nullable', 'string', 'max:64'], 'actor' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', Rule::in(['created', 'updated', 'deleted', 'restored'])],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = RecordLog::query();
        foreach (['resource', 'record_id', 'event'] as $key) if (!empty($data[$key])) $query->where($key, $data[$key]);
        if (!empty($data['actor'])) $query->where('actor_name', 'like', '%'.$data['actor'].'%');
        if (!empty($data['date_from'])) $query->whereDate('created_at', '>=', $data['date_from']);
        if (!empty($data['date_to'])) $query->whereDate('created_at', '<=', $data['date_to']);
        if (!$request->user()->can('support.ticket.internal')) $query->where('is_private', false);
        return response()->json(['data' => $query->latest('id')->paginate($data['per_page'] ?? 25), 'resources' => array_keys(config('audit.resources'))]);
    }
}
