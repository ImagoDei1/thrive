<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->enum('kyc_status', ['not_started', 'submitted', 'approved', 'rejected'])->default('not_started');
            $table->string('organisation_type', 30)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('physical_address')->nullable();
            $table->string('district', 100)->nullable();
            $table->string('id_type', 30)->nullable();
            $table->string('id_number', 50)->nullable();
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->foreignUuid('kyc_reviewed_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('kyc_rejection_reason')->nullable();

            $table->index('kyc_status');
        });

        Schema::create('provider_kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('provider_id')
                  ->references('id')->on('provider_profiles')
                  ->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->unique(['provider_id', 'type']);
        });

        Schema::table('channels', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->references('id')->on('users')->nullOnDelete();
        });

        // Providers approved before KYC existed keep streaming: treat them as verified.
        DB::table('provider_profiles')->where('is_approved', true)->update(['kyc_status' => 'approved']);

        DB::table('channels')
            ->whereIn('provider_id', DB::table('provider_profiles')->where('is_approved', true)->select('id'))
            ->update(['verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verified_at', 'verified_by']);
        });

        Schema::dropIfExists('provider_kyc_documents');

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropForeign(['kyc_reviewed_by']);
            $table->dropIndex(['kyc_status']);
            $table->dropColumn([
                'kyc_status', 'organisation_type', 'registration_number', 'physical_address', 'district',
                'id_type', 'id_number', 'kyc_submitted_at', 'kyc_reviewed_at', 'kyc_reviewed_by',
                'kyc_rejection_reason',
            ]);
        });
    }
};
