<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BillingPeriod;
use App\Services\BillingPeriodService;
use App\Services\RevenueCalculationService;
use Illuminate\Console\Command;

class ProcessScheduledPayouts extends Command
{
    protected $signature   = 'payouts:process-scheduled';
    protected $description = 'Close billing periods whose cutoff has passed and trigger payouts on payout_date';

    public function handle(
        BillingPeriodService $billingService,
        RevenueCalculationService $revenueService
    ): int {
        $today = now()->toDateString();

        // 1. Close any open period whose cutoff_date has passed
        $toClose = BillingPeriod::where('status', 'open')
            ->whereNotNull('cutoff_date')
            ->where('cutoff_date', '<', $today)
            ->get();

        foreach ($toClose as $period) {
            $this->info("Closing period {$period->period_label} (cutoff was {$period->cutoff_date})...");

            $revenueService->calculate($period);
            $period->update(['status' => 'closed', 'closed_at' => now()]);

            // Ensure the next period already exists
            $next = $billingService->ensureNextPeriodExists($period);
            $this->info("Next period {$next->period_label} ready (cutoff: {$next->cutoff_date}, payout: {$next->payout_date}).");
        }

        // 2. Trigger disbursement for periods whose payout_date is today
        $toPay = BillingPeriod::where('status', 'closed')
            ->whereNotNull('payout_date')
            ->where('payout_date', $today)
            ->get();

        foreach ($toPay as $period) {
            $this->info("Processing payouts for period {$period->period_label}...");

            // Mark all approved payouts as paid
            $paid = $period->payouts()
                ->where('status', 'approved')
                ->update(['status' => 'paid', 'paid_at' => now()]);

            $period->update(['status' => 'paid']);

            $this->info("Marked {$paid} payout(s) as paid for {$period->period_label}.");
        }

        return self::SUCCESS;
    }
}
