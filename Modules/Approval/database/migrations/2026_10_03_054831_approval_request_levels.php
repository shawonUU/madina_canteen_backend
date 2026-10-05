<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approval_request_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('approval_request_id')
                ->constrained('approval_requests');

            $table->unsignedInteger('level_no');

            $table->string('level_name');

            $table->enum('approver_type', [
                'Role',
                'User',
                'Department Role',
            ]);

            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable();

            $table->unsignedInteger('min_approvers')
                ->default(1);

            $table->enum('status', [
                'Waiting',
                'Pending',
                'Approved',
                'Rejected',
                'Returned',
                'Skipped',
            ])->default('Waiting');

            $table->foreignId('action_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('action_at')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique([
                'approval_request_id',
                'level_no',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
