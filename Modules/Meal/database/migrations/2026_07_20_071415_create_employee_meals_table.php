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
        Schema::create('employee_meals', function(Blueprint $table){

            $table->id();


            $table->bigInteger('employee_id')
                ->constrained('employees');


            $table->bigInteger('meal_type_id')
                ->constrained('meal_types');


            $table->decimal('meal_rate',10,2)
                ->default(0);


            $table->date('meal_date');


            $table->integer('quantity')
                ->default(1);


            $table->decimal('total_amount',10,2);


            $table->bigInteger('menu_id')
                ->nullable();


            $table->text('remarks')
                ->nullable();


            $table->enum('status',[
                'Selected',
                'Served',
                'Cancelled'
            ])
            ->default('Selected');


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
        Schema::dropIfExists('employee_meals');
    }
};
