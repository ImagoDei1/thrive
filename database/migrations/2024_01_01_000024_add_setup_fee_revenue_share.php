<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upfront setup fees are replaced by a reduced revenue share for a provider's first
 * months (max 6): e.g. on an 80/20 split the provider receives 70% and the platform
 * keeps the extra 10 points as the setup fee. The legacy setup_fee_status /
 * setup_fee_amount columns are left in place for historical records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Percentage points of attributable revenue withheld as the setup fee
            $table->decimal('setup_fee_percentage', 5, 2)->default(0)->after('setup_fee_amount');
            // Inclusive "YYYY-MM" billing period range the deduction applies to
            $table->string('setup_fee_starts_period', 7)->nullable()->after('setup_fee_percentage');
            $table->string('setup_fee_ends_period', 7)->nullable()->after('setup_fee_starts_period');
        });

        Schema::table('provider_payouts', function (Blueprint $table) {
            $table->decimal('gross_amount_mwk', 14, 2)->default(0)->after('share_percent');
            $table->decimal('setup_fee_percentage', 5, 2)->default(0)->after('gross_amount_mwk');
            $table->decimal('setup_fee_mwk', 14, 2)->default(0)->after('setup_fee_percentage');
        });

        Schema::table('billing_periods', function (Blueprint $table) {
            // Total setup fees withheld from provider payouts, retained by the platform
            $table->decimal('setup_fees_mwk', 14, 2)->default(0)->after('provider_pool');
        });

        DB::table('provider_payouts')->update(['gross_amount_mwk' => DB::raw('amount_mwk')]);
    }

    public function down(): void
    {
        Schema::table('billing_periods', function (Blueprint $table) {
            $table->dropColumn('setup_fees_mwk');
        });

        Schema::table('provider_payouts', function (Blueprint $table) {
            $table->dropColumn(['gross_amount_mwk', 'setup_fee_percentage', 'setup_fee_mwk']);
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn(['setup_fee_percentage', 'setup_fee_starts_period', 'setup_fee_ends_period']);
        });
    }
};
