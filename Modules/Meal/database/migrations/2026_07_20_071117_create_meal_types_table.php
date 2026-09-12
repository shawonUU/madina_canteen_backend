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
        Schema::create('meal_types', function(Blueprint $table){

            $table->id();

            $table->string('name');
            $table->string('slug')->unique();



            $table->text('description')
                ->nullable();

            $table->time('booking_cutoff_time');
            $table->decimal('meal_rate',10,2);

            $table->enum('status',[
                'Active',
                'Inactive'
            ])->default('Active');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_types');
    }
};
