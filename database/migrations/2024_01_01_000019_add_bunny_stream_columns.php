<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->string('bunny_library_id', 32)->nullable()->unique();
            // Library-scoped API key and CDN token key, stored encrypted (model casts).
            $table->text('bunny_library_api_key')->nullable();
            $table->text('bunny_token_key')->nullable();
            $table->string('bunny_cdn_hostname')->nullable();
        });

        Schema::table('channels', function (Blueprint $table) {
            // Reserved for Bunny live streaming (Phase 2).
            $table->string('bunny_stream_id', 64)->nullable();
        });

        Schema::table('content_items', function (Blueprint $table) {
            $table->string('bunny_library_id', 32)->nullable();
            $table->uuid('bunny_video_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropUnique(['bunny_video_id']);
            $table->dropColumn(['bunny_library_id', 'bunny_video_id']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('bunny_stream_id');
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropUnique(['bunny_library_id']);
            $table->dropColumn(['bunny_library_id', 'bunny_library_api_key', 'bunny_token_key', 'bunny_cdn_hostname']);
        });
    }
};
