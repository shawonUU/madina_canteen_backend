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
        Schema::create('employee_meal_items', function(Blueprint $table){

            $table->id();


            $table->bigInteger('employee_meal_id')
                ->constrained('employee_meals');


            $table->bigInteger('menu_item_id');


            $table->bigInteger('created_by')
                ->nullable();


            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_meal_items');
    }
};
