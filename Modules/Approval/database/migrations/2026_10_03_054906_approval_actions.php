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
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('approval_request_id')
                ->constrained('approval_requests');

            $table->foreignId('approval_request_level_id')
                ->nullable()
                ->constrained('approval_request_levels')
                ->nullOnDelete();

            $table->enum('action', [
                'Submitted',
                'Approved',
                'Rejected',
                'Returned',
                'Resubmitted',
                'Cancelled',
            ]);

            $table->foreignId('action_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index([
                'approval_request_id',
                'action',
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
