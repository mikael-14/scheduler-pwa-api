<?php

use App\Enums\ScheduleStatus;
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
        Schema::create('schedule_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ScheduleStatus::cases())->default(ScheduleStatus::Pending->value);
            $table->string('description')->nullable();
            $table->timestamps();
            // Prevents a user from being invited to the same schedule multiple times
            $table->unique(['schedule_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_users');
    }
};
