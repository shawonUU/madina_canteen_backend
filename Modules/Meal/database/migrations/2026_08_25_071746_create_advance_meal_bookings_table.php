<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_meal_bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('meal_type_id')
                ->constrained('meal_types')
                ->cascadeOnDelete();

            $table->date('booking_date');

            $table->enum('status', [
                'booked',
                'cancelled',
            ])->default('booked');

            $table->timestamps();

            $table->unique([
                'employee_id',
                'meal_type_id',
                'booking_date',
            ]);

            $table->index([
                'employee_id',
                'booking_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_meal_bookings');
    }
};