<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('record_logs')) return;
        Schema::create('record_logs', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 80);
            $table->string('record_id', 64);
            $table->string('parent_resource', 80)->nullable();
            $table->string('parent_id', 64)->nullable();
            $table->string('event', 20);
            $table->boolean('is_private')->default(false);
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['resource', 'record_id'], 'record_log_record_idx');
            $table->index(['parent_resource', 'parent_id'], 'record_log_parent_idx');
        });
    }

    public function down(): void
    {
        // Audit history is intentionally retained during rollback.
    }
};
