<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('event_id');
            $table->uuid('guest_profile_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->integer('total_cents')->default(0);
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $table->foreign('guest_profile_id')->references('id')->on('guest_profiles')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
