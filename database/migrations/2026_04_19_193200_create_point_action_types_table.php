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
        Schema::create('point_action_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_rule_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->string('description');

   
    $table->foreignId('point_category_id')
          ->constrained()
          ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_action_types');
    }
};
