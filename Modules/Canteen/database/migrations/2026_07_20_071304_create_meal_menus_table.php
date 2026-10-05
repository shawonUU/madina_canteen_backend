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
        Schema::create('meal_menus', function(Blueprint $table){
            $table->id();
            $table->string('code')->unique();
            $table->date('menu_date');
            $table->bigInteger('meal_type_id')->constrained('meal_types');
            $table->bigInteger('created_by')->nullable();
            $table->enum('status', [ 'Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed', ])->default('Pending');
            $table->timestamps();
            $table->unique([ 'menu_date', 'meal_type_id', ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_menus');
    }
};
