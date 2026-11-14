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

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('key')->unique();

            $table->foreignId('module_id')
                ->nullable()
                ->constrained('modules')
                ->nullOnDelete();

            $table->foreignId('menu_id')
                ->nullable()
                ->constrained('menus')
                ->nullOnDelete();

            $table->foreignId('child_menu_id')
                ->nullable()
                ->constrained('child_menus')
                ->nullOnDelete();

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
