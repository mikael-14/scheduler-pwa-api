<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who caused each notification and how it was delivered besides the in-app copy
     * (see App\Notifications\DeliveryLog). Rows from before this migration have no delivery data.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable()->after('notifiable_id')->constrained('users')->nullOnDelete();
            $table->string('mail_status', 16)->nullable()->after('data');
            $table->timestamp('mail_sent_at')->nullable()->after('mail_status');
            $table->string('push_status', 16)->nullable()->after('mail_sent_at');
            $table->timestamp('push_sent_at')->nullable()->after('push_status');
            $table->text('delivery_error')->nullable()->after('push_sent_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropConstrainedForeignId('sender_id');
            $table->dropColumn(['mail_status', 'mail_sent_at', 'push_status', 'push_sent_at', 'delivery_error']);
        });
    }
};
