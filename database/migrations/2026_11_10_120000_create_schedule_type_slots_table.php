<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift slots: a schedule type can offer fixed times (ScheduleTypeSlot) that people pick
 * instead of choosing their own. One schedule per slot; the others join it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_types', function (Blueprint $table) {
            $table->boolean('uses_slots')->default(false)->after('all_day');
        });

        Schema::create('schedule_type_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_type_id')->constrained('schedule_types')->cascadeOnDelete();
            $table->dateTime('start');
            $table->dateTime('end');
            $table->unsignedSmallInteger('capacity')->nullable(); // null = no limit
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['schedule_type_id', 'start']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('schedule_type_slot_id')->nullable()->after('schedule_type_id')
                ->constrained('schedule_type_slots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_type_slot_id');
        });

        Schema::dropIfExists('schedule_type_slots');

        Schema::table('schedule_types', function (Blueprint $table) {
            $table->dropColumn('uses_slots');
        });
    }
};
