<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_meal_id')->constrained('employee_meals')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->unique(['employee_meal_id','menu_item_id',]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_meal_items');
    }
};