<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->after('description');
            $table->dateTime('starts_at')->nullable()->after('is_published');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->integer('capacity')->nullable()->after('ends_at');
            $table->integer('price_cents')->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['status', 'starts_at', 'ends_at', 'capacity', 'price_cents']);
        });
    }
};
