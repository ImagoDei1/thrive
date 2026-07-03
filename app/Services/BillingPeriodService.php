<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillingPeriod;
use Carbon\Carbon;

class BillingPeriodService
{
    // Day of month that is the cut-off (configurable — admin can override per period)
    public const DEFAULT_CUTOFF_DAY = 20;

    /**
     * Calculate payout date: 2 business days after cutoff, skipping weekends.
     */
    public function payoutDateFromCutoff(Carbon $cutoff): Carbon
    {
        $date = $cutoff->copy();
        $businessDaysAdded = 0;

        while ($businessDaysAdded < 2) {
            $date->addDay();
            // 6 = Saturday, 0 = Sunday
            if (! in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                $businessDaysAdded++;
            }
        }

        return $date;
    }

    /**
     * Create a billing period for a given YYYY-MM label.
     * Cut-off day defaults to the 20th but can be overridden.
     */
    public function createPeriod(string $periodLabel, int $cutoffDay = self::DEFAULT_CUTOFF_DAY): BillingPeriod
    {
        $start      = Carbon::createFromFormat('Y-m', $periodLabel)->startOfMonth();
        $end        = $start->copy()->endOfMonth();
        $cutoff     = $start->copy()->setDay(min($cutoffDay, $start->daysInMonth));
        $payoutDate = $this->payoutDateFromCutoff($cutoff);

        return BillingPeriod::create([
            'period_label'        => $periodLabel,
            'starts_at'           => $start->toDateString(),
            'ends_at'             => $end->toDateString(),
            'cutoff_date'         => $cutoff->toDateString(),
            'payout_date'         => $payoutDate->toDateString(),
            'platform_percentage' => 20.00,
            'status'              => 'open',
        ]);
    }

    /**
     * Ensure the next billing period exists — called when a period closes.
     */
    public function ensureNextPeriodExists(BillingPeriod $current): BillingPeriod
    {
        $nextLabel = Carbon::createFromFormat('Y-m', $current->period_label)
            ->addMonth()
            ->format('Y-m');

        return BillingPeriod::firstOrCreate(
            ['period_label' => $nextLabel],
            fn () => $this->createPeriod($nextLabel)->toArray()
        );
    }

    /**
     * Find the open billing period a payment belongs to based on its payment date.
     * If the payment date is after the cutoff of the current open period,
     * it belongs to the next period.
     */
    public function periodForPaymentDate(Carbon $paymentDate): ?BillingPeriod
    {
        // Find a period whose window contains this payment date and is before/on cutoff
        $period = BillingPeriod::where('status', 'open')
            ->where('starts_at', '<=', $paymentDate->toDateString())
            ->where('cutoff_date', '>=', $paymentDate->toDateString())
            ->first();

        if ($period) {
            return $period;
        }

        // Payment is past the cutoff — find or create the next open period
        $nextPeriod = BillingPeriod::where('status', 'open')
            ->where('starts_at', '>', $paymentDate->toDateString())
            ->orderBy('starts_at')
            ->first();

        if ($nextPeriod) {
            return $nextPeriod;
        }

        // Auto-create the next period if none exists
        $latestPeriod = BillingPeriod::orderByDesc('period_label')->first();
        if ($latestPeriod) {
            return $this->ensureNextPeriodExists($latestPeriod);
        }

        return null;
    }
}
