<?php

namespace App\Observers;

use App\Models\RecordLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecordLogObserver
{
    private array $columns = [];

    public function creating(Model $model): void
    {
        if (auth()->id() && $this->hasColumn($model, 'created_by')) $model->setAttribute('created_by', auth()->id());
        if (auth()->id() && $this->hasColumn($model, 'updated_by')) $model->setAttribute('updated_by', auth()->id());
    }

    public function updating(Model $model): void
    {
        if ($this->hasColumn($model, 'created_by')) $model->setAttribute('created_by', $model->getRawOriginal('created_by'));
        if (auth()->id() && $this->hasColumn($model, 'updated_by')) $model->setAttribute('updated_by', auth()->id());
    }

    public function created(Model $model): void { $this->record($model, 'created', [], $model->getAttributes()); }
    public function deleted(Model $model): void { $this->record($model, 'deleted', $model->getRawOriginal(), []); }
    public function restored(Model $model): void { $this->record($model, 'restored', $model->getRawOriginal(), $model->getAttributes()); }

    public function updated(Model $model): void
    {
        $fields = array_keys(array_diff_key($model->getChanges(), array_flip(['updated_at', 'updated_by'])));
        if (!$fields) return;
        $this->record($model, 'updated', $model->getRawOriginal(), $model->getAttributes(), $fields);
    }

    private function hasColumn(Model $model, string $column): bool
    {
        $key = $model->getConnectionName().'.'.$model->getTable();
        $this->columns[$key] ??= Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());
        return in_array($column, $this->columns[$key], true);
    }

    private function clean(array $values): array
    {
        foreach (config('audit.redacted', []) as $key) {
            if (array_key_exists($key, $values)) $values[$key] = '[REDACTED]';
        }
        return $values;
    }

    private function record(Model $model, string $event, array $before, array $after, ?array $fields = null): void
    {
        $resource = $model->getTable();
        $meta = config('audit.resources')[$resource] ?? null;
        if (!$meta) return;
        // Migrations/seeding may run before the log table exists; normal API writes must have migrated schema.
        if (app()->runningInConsole() && !Schema::hasTable('record_logs')) return;
        $request = request();
        $requestId = $request->attributes->get('audit_request_id');
        if (!$requestId) {
            $requestId = (string) Str::uuid();
            $request->attributes->set('audit_request_id', $requestId);
        }
        $parentId = isset($meta[3]) ? ($after[$meta[3]] ?? $before[$meta[3]] ?? null) : null;
        $private = (bool) ($after['is_internal'] ?? $before['is_internal'] ?? false);
        if ($resource === 'support_ticket_attachments' && $model->reply_id) {
            $private = (bool) \Modules\Support\Models\SupportTicketReply::whereKey($model->reply_id)->value('is_internal');
        }
        RecordLog::create([
            'resource' => $resource, 'record_id' => (string) $model->getKey(),
            'parent_resource' => $meta[2] ?? null, 'parent_id' => $parentId,
            'event' => $event, 'actor_id' => auth()->id(), 'actor_name' => auth()->user()?->name ?? 'System',
            'is_private' => $private,
            'old_values' => $this->clean($before), 'new_values' => $this->clean($after),
            'changed_fields' => $fields ?? array_keys($after ?: $before),
            'request_id' => $requestId, 'ip_address' => app()->runningInConsole() ? null : $request->ip(),
        ]);
    }
}
