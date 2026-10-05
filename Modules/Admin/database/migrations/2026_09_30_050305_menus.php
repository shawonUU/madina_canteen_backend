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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();
            $table->foreignId('module_id');
            $table->string('name');
            $table->string('slug');

            $table->string('route')->nullable();

            $table->string('permission')->nullable();

            $table->string('icon')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['module_id', 'slug']);

            $table->index([
                'module_id',
                'sort_order',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
