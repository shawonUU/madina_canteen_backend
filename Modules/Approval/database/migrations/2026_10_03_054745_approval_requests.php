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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('workflow_id')
                ->constrained('approval_workflows')
                ->restrictOnDelete();

            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('document_no')->nullable();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedInteger('current_level')
                ->default(1);

            $table->enum('status', [
                'Draft',
                'Pending',
                'Approved',
                'Rejected',
                'Returned',
                'Cancelled',
            ])->default('Draft');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index([
                'document_type',
                'document_id',
            ]);

            $table->index([
                'status',
                'current_level',
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
