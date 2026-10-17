<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Schema builder cannot alter a PostgreSQL enum (check constraint), so the
    // constraint is swapped directly.
    public function up(): void
    {
        // 'failed': a checkout whose mobile-money charge was declined or timed out.
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('active', 'expired', 'cancelled', 'pending', 'failed'))");
    }

    public function down(): void
    {
        DB::table('subscriptions')->where('status', 'failed')->update(['status' => 'cancelled']);

        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check');
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('active', 'expired', 'cancelled', 'pending'))");
    }
};
