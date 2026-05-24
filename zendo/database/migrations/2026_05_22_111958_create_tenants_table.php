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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('custom_domain')->nullable();
            $table->json('features')->default('{}');
            $table->enum('registration_mode', ['AUTO_CONFIRM', 'MANUAL_REVIEW', 'AUTO_IF_PAID'])
                ->default('MANUAL_REVIEW');
            $table->string('currency', 3)->default('EUR');
            $table->string('timezone')->default('Europe/Paris');
            $table->string('locale', 5)->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
