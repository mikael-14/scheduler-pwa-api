<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The coverage check can run several times a day (coverage.run_at): each run sends its own
     * summaries, so a row also says which run ("09:00") it was sent by. The rows so far came from
     * the single daily run of 09:00.
     */
    public function up(): void
    {
        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->string('run_at', 5)->default('09:00')->after('sent_on');
        });

        DB::table('coverage_alerts')->update(['run_at' => '09:00']);

        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->dropUnique(['rule', 'date', 'level', 'sent_on']);
            $table->unique(['rule', 'date', 'level', 'sent_on', 'run_at']);
        });
    }

    public function down(): void
    {
        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->dropUnique(['rule', 'date', 'level', 'sent_on', 'run_at']);
        });

        // the old key allows one row per rule, day, summary and morning
        // ids read first: MySQL refuses a delete with a subquery on the same table
        $keep = DB::table('coverage_alerts')->selectRaw('MIN(id) as id')->groupBy('rule', 'date', 'level', 'sent_on')->pluck('id');
        DB::table('coverage_alerts')->whereNotIn('id', $keep)->delete();

        Schema::table('coverage_alerts', function (Blueprint $table) {
            $table->unique(['rule', 'date', 'level', 'sent_on']);
            $table->dropColumn('run_at');
        });
    }
};
