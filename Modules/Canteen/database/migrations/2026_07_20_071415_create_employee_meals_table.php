<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_meals', function (Blueprint $table) {

            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('meal_type_id')->constrained('meal_types');
            $table->date('meal_date');
            $table->decimal('meal_rate', 10, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->foreignId('menu_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->enum('status', ['Selected','Served','Cancelled',])->default('Selected');
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->unique(['employee_id','meal_type_id','meal_date',]);
            $table->index(['employee_id','meal_date',]);
            $table->index(['meal_date','meal_type_id','status',]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_meals');
    }
};