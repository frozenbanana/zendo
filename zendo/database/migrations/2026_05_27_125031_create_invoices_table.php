<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('registration_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('status')->default('PENDING');
            $table->integer('total_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('registration_id')->references('id')->on('registrations')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
