<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dietary_tag_meal_plan', function (Blueprint $table) {
            $table->uuid('dietary_tag_id');
            $table->uuid('meal_plan_id');
            $table->timestamps();

            $table->primary(['dietary_tag_id', 'meal_plan_id']);
            $table->foreign('dietary_tag_id')->references('id')->on('dietary_tags')->cascadeOnDelete();
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dietary_tag_meal_plan');
    }
};
