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
        Schema::create('menu_items', function(Blueprint $table){
            $table->id();
            $table->bigInteger('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('name');
            $table->enum('item_type',['Main','Alternative' ])->default('Main');
            $table->foreignId('alternative_of')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->timestamps();
            $table->index(['menu_id','item_type',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
