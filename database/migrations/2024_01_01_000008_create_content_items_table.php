<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['vod', 'live_recording', 'short'])->default('vod');
            $table->string('file_path')->nullable();
            $table->string('hls_url')->nullable();
            $table->string('dash_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->bigInteger('file_size_bytes')->default(0);
            $table->enum('status', ['uploading', 'processing', 'ready', 'failed'])->default('uploading');
            $table->string('resolution')->nullable();
            $table->json('qualities')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('channel_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
