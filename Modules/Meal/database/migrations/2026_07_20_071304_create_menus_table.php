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
        Schema::create('menus', function(Blueprint $table){
            $table->id();
            $table->date('menu_date');
            $table->bigInteger('meal_type_id')->constrained('meal_types');
            $table->bigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique([ 'menu_date', 'meal_type_id', ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
