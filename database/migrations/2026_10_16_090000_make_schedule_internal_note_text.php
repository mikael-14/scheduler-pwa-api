<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Decisions append their message to the note, so 255 characters is no longer enough. */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->text('internal_note')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('internal_note')->nullable()->change();
        });
    }
};
