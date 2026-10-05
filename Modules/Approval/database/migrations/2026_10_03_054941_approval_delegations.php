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
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('from_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('to_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('start_at');
            $table->timestamp('end_at');

            $table->text('reason')->nullable();

            $table->enum('status', [
                'Active',
                'Inactive',
                'Expired',
                'Cancelled',
            ])->default('Active');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'from_user_id',
                'status',
                'start_at',
                'end_at',
            ]);

            $table->index([
                'to_user_id',
                'status',
                'start_at',
                'end_at',
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
