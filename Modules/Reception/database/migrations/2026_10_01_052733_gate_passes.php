<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('gate_passes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('pass_no')->unique();
            $table->enum('gate_pass_type', ['Person','Person With Material',]);
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('department_id')->nullable();
            $table->string('department_name');
            $table->foreignId('designation_id')->nullable();
            $table->string('designation_name');
            $table->string('purpose');
            $table->dateTime('expected_exit_at');
            $table->dateTime('expected_return_at')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', [ 'Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed', ])->default('Pending');
            $table->timestamps();
            $table->index(['gate_pass_type', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
