<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How someone gets the emails that can wait (see AppNotification::digestible): each one right away
 * ('instant'), or gathered in a summary twice a day ('twice', the default) or once a day ('daily').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_digest', 10)->default('twice')->after('all_email_notification');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_digest');
        });
    }
};
