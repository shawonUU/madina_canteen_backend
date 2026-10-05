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
        Schema::create('gate_pass_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('gate_pass_id')->constrained('gate_passes');
            $table->foreignId('product_id')->nullable();
            $table->string('product_name');
            $table->decimal('quantity', 15, 2)->default(1);
            $table->string('unit', 50)->nullable();
            $table->string('asset_no')->nullable();
            $table->string('serial_no')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index('gate_pass_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
