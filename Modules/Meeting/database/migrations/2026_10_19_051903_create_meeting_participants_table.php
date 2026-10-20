
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')
                ->constrained('meetings')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('employee_id')->nullable()->index();
            
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->string('guest_company')->nullable();

            $table->string('participation_type', 20)->default('required');
            $table->string('invitation_status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();

            $table->timestamps();

            $table->unique(['meeting_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
