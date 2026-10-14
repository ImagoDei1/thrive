<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlists', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            // Display hint only: 'series' shows season tabs and S1 E3 labels in the apps.
            $table->enum('kind', ['playlist', 'series'])->default('playlist');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('channel_id');
            $table->index('is_published');
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('content_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            // Only meaningful when the playlist kind is 'series'.
            $table->unsignedSmallInteger('season_number')->nullable();
            $table->timestamps();

            $table->unique(['playlist_id', 'content_item_id']);
            $table->index(['playlist_id', 'position']);
            $table->index('content_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlists');
    }
};
