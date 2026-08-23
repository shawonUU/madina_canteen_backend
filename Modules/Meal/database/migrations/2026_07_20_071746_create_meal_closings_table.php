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
        Schema::create('meal_closings', function(Blueprint $table){

            $table->id();


            $table->date('meal_date');


            $table->bigInteger('meal_type_id')
                ->constrained('meal_types');


            $table->enum('closing_type',[
                'Daily',
                'Monthly'
            ]);


            $table->enum('status',[
                'Open',
                'Closed',
                'Reopen'
            ])
            ->default('Closed');


            $table->bigInteger('closed_by');


            $table->timestamp('closed_at')
                ->nullable();


            $table->text('remarks')
                ->nullable();


            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_closings');
    }
};
