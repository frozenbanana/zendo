<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('building_id');
            $table->string('name');
            $table->integer('capacity')->default(1);
            $table->string('room_type', 20)->default('single');
            $table->timestamps();

            $table->foreign('building_id')->references('id')->on('buildings')->cascadeOnDelete();
            $table->index('building_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
