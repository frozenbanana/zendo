<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->uuid('event_instance_id')->nullable()->after('event_id');
            $table->string('guest_first_name')->nullable()->after('user_id');
            $table->string('guest_last_name')->nullable()->after('guest_first_name');
            $table->string('guest_email')->nullable()->after('guest_last_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->string('status', 30)->default('PENDING')->change();

            $table->foreign('event_instance_id')->references('id')->on('event_instances')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['event_instance_id']);
            $table->dropColumn([
                'event_instance_id',
                'guest_first_name',
                'guest_last_name',
                'guest_email',
                'guest_phone',
            ]);
        });
    }
};
