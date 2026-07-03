<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->enum('setup_fee_status', ['pending', 'paid', 'waived'])->default('pending');
            $table->decimal('setup_fee_amount', 10, 2)->default(0);
            $table->string('bank_account')->nullable();
            $table->string('mobile_money_number', 20)->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('is_approved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};
