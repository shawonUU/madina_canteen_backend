<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (array_keys(config('audit.resources', [])) as $table) {
            if (!Schema::hasTable($table)) continue;
            foreach (['created_by', 'updated_by'] as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger($column)->nullable()->index());
                }
            }
            if (!Schema::hasColumn($table, 'approval_status')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('approval_status', 30)->nullable());
            }
        }
        if (!Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', fn (Blueprint $table) => $table->rememberToken());
        }
    }

    public function down(): void
    {
        // Existing audit attribution and approval values must not be dropped.
    }
};
