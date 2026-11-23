<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coverage alerts became summaries (config/coverage.php): one urgent and one look-ahead per
     * rule and run, each listing its days. A row is still one day of one summary, now with which
     * summary (`level`) and the day it was sent (`sent_on`): the urgent one goes out once a morning,
     * the look-ahead one only when it has a day not announced before. `days_before` stays: how many
     * days ahead the day was when it was sent.
     */
    public function up(): void
    {
        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->string('level', 10)->default('ahead')->after('days_before');
            $table->date('sent_on')->nullable()->after('level');
        });

        // the alerts sent so far: the day before = urgent, the others = look-ahead
        DB::table('coverage_alerts')->orderBy('id')->each(function ($row) {
            DB::table('coverage_alerts')->where('id', $row->id)->update([
                'level' => (int) $row->days_before <= 1 ? 'urgent' : 'ahead',
                'sent_on' => Carbon::parse($row->date)->subDays((int) $row->days_before)->toDateString(),
            ]);
        });

        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->dropUnique(['rule', 'date', 'days_before']);
            $table->unique(['rule', 'date', 'level', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->dropUnique(['rule', 'date', 'level', 'sent_on']);
        });

        // the old key allows one row per rule, day and "days before"
        // ids read first: MySQL refuses a delete with a subquery on the same table
        $keep = DB::table('coverage_alerts')->selectRaw('MIN(id) as id')->groupBy('rule', 'date', 'days_before')->pluck('id');
        DB::table('coverage_alerts')->whereNotIn('id', $keep)->delete();

        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->unique(['rule', 'date', 'days_before']);
            $table->dropColumn(['level', 'sent_on']);
        });
    }
};
