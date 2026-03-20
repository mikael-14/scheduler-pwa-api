<?php

use App\Enums\ScheduleStatus;
use App\Models\ScheduleType;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('time');
            $table->string('description')->nullable();
            $table->string('internal_note')->nullable();
            $table->enum('status', ScheduleStatus::cases())->default(ScheduleStatus::Pending->value);
            $table->foreignIdFor(User::class)->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(ScheduleType::class)->nullable()->constrained('schedule_types')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduler');
    }
};
