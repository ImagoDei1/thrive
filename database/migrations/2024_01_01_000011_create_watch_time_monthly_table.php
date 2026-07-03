<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_time_monthly', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->string('billing_period', 7);
            $table->integer('total_seconds')->default(0);
            $table->decimal('total_hours', 10, 4)->default(0);
            $table->timestamps();

            $table->unique(['channel_id', 'billing_period']);
            $table->index('billing_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_time_monthly');
    }
};
