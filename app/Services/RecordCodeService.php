<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RecordCodeService
{
    public function next(string $class, string $column, string $prefix): string
    {
        $model = new $class;
        $name = $model->getTable().':'.$column.':'.$prefix;
        return DB::transaction(function () use ($model, $name, $column, $prefix) {
            if (!DB::table('record_sequences')->where('name', $name)->exists()) {
                $last = 0;
                foreach ($model->newQueryWithoutScopes()->where($column, 'like', $prefix.'-%')->select($column)->cursor() as $row) {
                    if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', (string) $row->{$column}, $match)) $last = max($last, (int) $match[1]);
                }
                DB::table('record_sequences')->insertOrIgnore(['name' => $name, 'last_value' => $last]);
            }
            $sequence = DB::table('record_sequences')->where('name', $name)->lockForUpdate()->first();
            $next = (int) $sequence->last_value + 1;
            DB::table('record_sequences')->where('name', $name)->update(['last_value' => $next]);
            return $prefix.'-'.$next;
        }, 3);
    }
}
