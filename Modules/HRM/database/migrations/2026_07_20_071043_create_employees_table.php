<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();

            $table->enum('status', ['Active', 'Inactive'])
                ->default('Active');

            $table->timestamps();

            $table->index('department');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};