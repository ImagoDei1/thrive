<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('billing_period_id')
                ->nullable()
                ->references('id')->on('billing_periods')
                ->nullOnDelete()
                ->after('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['billing_period_id']);
            $table->dropColumn('billing_period_id');
        });
    }
};
