<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_periods', function (Blueprint $table) {
            $table->date('cutoff_date')->nullable()->after('ends_at');
            $table->date('payout_date')->nullable()->after('cutoff_date');
        });
    }

    public function down(): void
    {
        Schema::table('billing_periods', function (Blueprint $table) {
            $table->dropColumn(['cutoff_date', 'payout_date']);
        });
    }
};
