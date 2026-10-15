<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Days a subscriber stays "active" after ends_at before being marked expired.
            // Short passes (24h, 1 week) get none; monthly and longer keep the original 3 days.
            $table->unsignedSmallInteger('grace_days')->default(0)->after('duration_days');
        });

        DB::table('subscription_plans')
            ->where('duration_days', '>=', 30)
            ->update(['grace_days' => 3]);
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('grace_days');
        });
    }
};
