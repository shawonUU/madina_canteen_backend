<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('record_sequences')) return;
        Schema::create('record_sequences', function (Blueprint $table) {
            $table->string('name', 191)->primary();
            $table->unsignedBigInteger('last_value')->default(0);
        });
    }
    public function down(): void {}
};
