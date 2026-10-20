
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('meeting_number', 50)->unique();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('meeting_type', 50)->default('internal');
            $table->foreignId('organizer_id')->nullable()->index();
            $table->foreignId('chairperson_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->date('meeting_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('status', 30)->default('scheduled')->index();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['meeting_date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
