<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('meeting_agendas', 'presenter')) {
            Schema::table('meeting_agendas', fn (Blueprint $table) => $table->string('presenter')->nullable());
        }
        if (!Schema::hasColumn('meeting_agendas', 'duration_minutes')) {
            Schema::table('meeting_agendas', fn (Blueprint $table) => $table->unsignedInteger('duration_minutes')->nullable());
        }
    }

    public function down(): void
    {
        // Keep populated agenda details when rolling back the application patch.
    }
};
