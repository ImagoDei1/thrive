<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('content_id')
                  ->nullable()
                  ->references('id')->on('content_items')
                  ->nullOnDelete();
            $table->enum('content_type', ['live', 'vod'])->default('vod');
            $table->integer('total_seconds')->default(0);
            $table->integer('last_position')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->enum('platform', ['android', 'ios', 'web', 'tv'])->nullable();
            $table->string('quality')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('channel_id');
            $table->index('content_id');
            $table->index('is_active');
            $table->index(['channel_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_sessions');
    }
};
