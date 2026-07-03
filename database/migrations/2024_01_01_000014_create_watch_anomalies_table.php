<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_anomalies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('session_id')
                  ->references('id')->on('watch_sessions')
                  ->cascadeOnDelete();
            $table->integer('position_delta')->default(0);
            $table->integer('actual_elapsed')->default(0);
            $table->integer('discrepancy_seconds')->default(0);
            $table->enum('type', ['skip', 'fast_forward', 'abnormal_session'])->default('skip');
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index('session_id');
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_anomalies');
    }
};
