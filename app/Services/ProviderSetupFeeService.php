<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProviderProfile;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Providers pay no upfront setup fee. Instead, a number of percentage points of
 * their revenue share is withheld for their first billing periods (max 6 months).
 * RevenueCalculationService applies the deduction when payouts are calculated.
 */
class ProviderSetupFeeService
{
    /**
     * Default terms on KYC approval: config percentage for config months, from the approval month.
     */
    public function assignDefaults(ProviderProfile $provider): void
    {
        if ($provider->hasSetupFeeTerms()) {
            return;
        }

        $this->assign(
            $provider,
            (float) config('revenue.setup_fee.percentage'),
            now()->format('Y-m'),
            (int) config('revenue.setup_fee.months'),
        );
    }

    /**
     * @param  string  $startsPeriod  first billing period ("YYYY-MM") the deduction applies to
     * @param  int  $months  number of billing periods, 0 removes the setup fee
     */
    public function assign(ProviderProfile $provider, float $percentage, string $startsPeriod, int $months): void
    {
        if ($months === 0 || $percentage <= 0) {
            $provider->update([
                'setup_fee_percentage'    => 0,
                'setup_fee_starts_period' => null,
                'setup_fee_ends_period'   => null,
            ]);

            return;
        }

        $maxMonths     = (int) config('revenue.setup_fee.max_months');
        $maxPercentage = (float) config('revenue.setup_fee.max_percentage');

        if ($months < 1 || $months > $maxMonths) {
            throw new InvalidArgumentException("The setup fee period must be between 1 and {$maxMonths} months.");
        }

        if ($percentage > $maxPercentage) {
            throw new InvalidArgumentException("The setup fee cannot exceed {$maxPercentage} percentage points.");
        }

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $startsPeriod)) {
            throw new InvalidArgumentException('The start period must be a YYYY-MM billing period.');
        }

        $endsPeriod = Carbon::createFromFormat('!Y-m', $startsPeriod)->addMonthsNoOverflow($months - 1)->format('Y-m');

        $provider->update([
            'setup_fee_percentage'    => $percentage,
            'setup_fee_starts_period' => $startsPeriod,
            'setup_fee_ends_period'   => $endsPeriod,
        ]);
    }
}
