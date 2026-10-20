
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_agendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')
                ->constrained('meetings')
                ->cascadeOnDelete();

            $table->string('title');
            $table->longText('description')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedBigInteger('presenter_id')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_agendas');
    }
};
