<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            // Set by the provider to hide a video without deleting it. Kept separate
            // from published_at, which the Bunny sync fills in when a video becomes ready.
            $table->timestamp('unpublished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropColumn('unpublished_at');
        });
    }
};
