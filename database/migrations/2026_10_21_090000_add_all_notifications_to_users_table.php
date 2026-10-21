<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "All notifications": also hear about other people's schedules (booked, approved, changed,
 * cancelled / rejected, people joining or leaving), by push and / or email. On for everyone,
 * existing accounts included; each person can turn them off in the app or the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('all_push_notification')->default(true)->after('push_notification');
            $table->boolean('all_email_notification')->default(true)->after('all_push_notification');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['all_push_notification', 'all_email_notification']);
        });
    }
};
