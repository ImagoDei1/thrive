<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('period_label', 7)->unique();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->integer('total_subscribers')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('platform_share', 14, 2)->default(0);
            $table->decimal('provider_pool', 14, 2)->default(0);
            $table->decimal('platform_percentage', 5, 2)->default(20.00);
            $table->enum('status', ['open', 'calculating', 'closed', 'paid'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
