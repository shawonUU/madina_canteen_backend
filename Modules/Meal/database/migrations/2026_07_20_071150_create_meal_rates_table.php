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
        Schema::create('meal_rates', function(Blueprint $table){
            $table->id();
            $table->bigInteger('meal_type_id')
                ->constrained('meal_types')
                ->cascadeOnDelete();
            $table->decimal('rate',10,2);
            $table->date('effective_date')->nullable();
            $table->enum('status',[
                'Active',
                'Inactive'
            ])->default('Active');
            $table->bigInteger('created_by')
                ->nullable();
            $table->timestamps();
            $table->index([ 'meal_type_id', 'effective_date', 'status' ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_rates');
    }
};
