<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('registration_id');
            $table->uuid('room_id')->nullable();
            $table->string('room_type')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->integer('price_cents')->default(0);
            $table->timestamps();

            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $table->unique('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stays');
    }
};
