<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A participant who cancels (or is taken off) is kept, marked cancelled, instead of being deleted:
 * ScheduleUser soft-deletes into this column. Rejoining restores the same row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_users', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_users', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
