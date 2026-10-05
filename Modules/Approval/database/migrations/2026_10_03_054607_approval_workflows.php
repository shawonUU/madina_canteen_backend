<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('module_id')
                ->constrained('modules');

            $table->foreignId('menu_id')
                ->nullable()
                ->constrained('menus');

            $table->foreignId('child_menu_id')
                ->nullable()
                ->constrained('child_menus');

            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('status', [
                'Active',
                'Inactive',
            ])->default('Active');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'module_id',
                'menu_id',
                'child_menu_id',
            ]);

            $table->unique([
                'module_id',
                'menu_id',
                'child_menu_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
