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
        Schema::create('approval_workflow_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('workflow_id')
                ->constrained('approval_workflows');

            $table->unsignedInteger('level_no');

            $table->string('name');

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

            $table->boolean('is_required')
                ->default(true);

            $table->boolean('can_reject')
                ->default(true);

            $table->boolean('can_return')
                ->default(true);

            $table->enum('status', [
                'Active',
                'Inactive',
            ])->default('Active');

            $table->timestamps();

            $table->unique([
                'workflow_id',
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
