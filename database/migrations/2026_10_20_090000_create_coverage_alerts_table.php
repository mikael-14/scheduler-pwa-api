<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coverage alerts already sent (config/coverage.php): one per rule, day and "days before",
     * so running the check twice never warns twice. Also the history of uncovered days.
     */
    public function up(): void
    {
        Schema::create('coverage_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('rule', 100);
            $table->foreignId('schedule_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('days_before');
            $table->unsignedInteger('approved_count');
            $table->unsignedInteger('pending_count');
            $table->unsignedInteger('recipients');
            $table->timestamps();

            $table->unique(['rule', 'date', 'days_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_alerts');
    }
};
