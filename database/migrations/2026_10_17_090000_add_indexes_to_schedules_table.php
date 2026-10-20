<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - (status, start): the pending list, the Approvals badge, the approver notifications and the
     *   dashboard widgets all filter on status and sort or bound on start.
     * - start: the calendars (panel and PWA) load a date window.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['status', 'start']);
            $table->index('start');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['status', 'start']);
            $table->dropIndex(['start']);
        });
    }
};
