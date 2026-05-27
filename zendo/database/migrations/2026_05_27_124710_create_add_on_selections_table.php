<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('add_on_selections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('registration_id');
            $table->string('add_on_type');
            $table->string('add_on_name');
            $table->integer('quantity')->default(1);
            $table->integer('price_cents')->default(0);
            $table->timestamps();

            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('add_on_selections');
    }
};
