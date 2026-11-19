<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email verification starts now (User implements MustVerifyEmail): the accounts that already
 * exist count as verified, so nobody who uses the app today is locked out by it. Only accounts
 * made from now on with a password (or a changed email) must confirm their address.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // nothing to undo: which accounts were unverified before is not kept
    }
};
