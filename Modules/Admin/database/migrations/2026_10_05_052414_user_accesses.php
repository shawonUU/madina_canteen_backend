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
        Schema::create('user_accesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('module_id')
                ->constrained('modules')
                ->cascadeOnDelete();

            $table->foreignId('menu_id')
                ->constrained('menus')
                ->cascadeOnDelete();

            $table->foreignId('child_menu_id')
                ->nullable()
                ->constrained('child_menus')
                ->cascadeOnDelete();

            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);

            $table->timestamps();

            $table->unique([
                'user_id',
                'menu_id',
                'child_menu_id',
            ]);

            $table->index([
                'user_id',
                'module_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
