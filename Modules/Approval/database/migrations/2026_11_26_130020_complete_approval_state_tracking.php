<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['can_reject', 'can_return'] as $column) {
            if (!Schema::hasColumn('approval_request_levels', $column)) {
                Schema::table('approval_request_levels', fn (Blueprint $table) => $table->boolean($column)->default(true));
            }
        }
        // Returned is already an approval action; store that existing outcome on its document.
        Schema::table('gate_passes', fn (Blueprint $table) => $table->string('approval_status', 30)->default('Pending')->change());
    }
    public function down(): void {}
};
