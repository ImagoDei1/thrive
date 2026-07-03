<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')
                  ->references('id')->on('subscriptions')
                  ->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('MWK');
            $table->string('paychangu_tx_id')->nullable()->unique();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['mobile_money', 'card', 'bank'])->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('paychangu_tx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
