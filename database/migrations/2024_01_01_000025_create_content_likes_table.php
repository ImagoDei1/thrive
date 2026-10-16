<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_likes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('content_item_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One like per user per video; also serves lookups by user.
            $table->unique(['user_id', 'content_item_id']);
            $table->index('content_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_likes');
    }
};
