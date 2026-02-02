<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('authentication_log', function (Blueprint $table) {

            if (! Schema::hasColumn('authentication_log', 'device_id')) {
                $table->string('device_id')->nullable()->after('ip_address');
            }

            if (! Schema::hasColumn('authentication_log', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('logout_at');
            }

            if (! Schema::hasColumn('authentication_log', 'device_name')) {
                $table->string('device_name')->nullable()->after('device_id');
            }

            // Optional but recommended for v6 consistency
            if (! Schema::hasColumn('authentication_log', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('authentication_log', 'is_trusted')) {
                $table->boolean('is_trusted')->default(0)->after('cleared_by_user');
            }
            if (!Schema::hasColumn('authentication_log', 'is_suspicious')) {
                $table->boolean('is_suspicious')->default(0)->after('is_trusted');
            }
            if (!Schema::hasColumn('authentication_log', 'suspicious_reason')) {
                $table->string('suspicious_reason')->nullable()->after('is_suspicious');
            }
        });
    }

    public function down(): void
    {
        Schema::table('authentication_log', function (Blueprint $table) {
            $table->dropColumn([
                'device_id',
                'last_activity_at',
                'device_name',
                'is_trusted',
                'is_suspicious',
                'suspicious_reason'
            ]);
        });
    }
};
