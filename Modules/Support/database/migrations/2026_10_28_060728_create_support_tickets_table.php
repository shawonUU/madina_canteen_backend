<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->longText('description');

            $table->string('module_name')->nullable();

            $table->string('issue_type')->default('bug');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');

            $table->foreignId('submitted_by')
                ->constrained('users');

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users');

            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['submitted_by', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};