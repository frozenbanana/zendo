<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_selections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('registration_id');
            $table->uuid('meal_plan_id');
            $table->date('date');
            $table->string('meal_type')->default('dinner');
            $table->json('dietary_tags')->default('[]');
            $table->integer('price_cents')->default(0);
            $table->timestamps();

            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->cascadeOnDelete();
            $table->index(['registration_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_selections');
    }
};
