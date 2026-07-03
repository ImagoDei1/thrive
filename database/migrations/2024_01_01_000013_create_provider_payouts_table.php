<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('provider_id')
                  ->references('id')->on('provider_profiles')
                  ->cascadeOnDelete();
            $table->foreignUuid('billing_period_id')
                  ->references('id')->on('billing_periods')
                  ->cascadeOnDelete();
            $table->decimal('watch_seconds', 14, 0)->default(0);
            $table->decimal('watch_hours', 10, 4)->default(0);
            $table->decimal('share_percent', 8, 6)->default(0);
            $table->decimal('amount_mwk', 14, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'paid', 'failed'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'billing_period_id']);
            $table->index('status');
            $table->index('billing_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payouts');
    }
};
