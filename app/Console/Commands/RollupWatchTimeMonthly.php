<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WatchTimeMonthly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RollupWatchTimeMonthly extends Command
{
    protected $signature   = 'watchtime:rollup-monthly {--period= : YYYY-MM period to roll up (defaults to current month)}';
    protected $description = 'Aggregate watch_time_daily into watch_time_monthly for a billing period';

    public function handle(): int
    {
        $period = $this->option('period') ?? now()->format('Y-m');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error("Invalid period format. Use YYYY-MM (e.g. 2026-06).");
            return self::FAILURE;
        }

        [$year, $month] = explode('-', $period);
        $startDate = "{$period}-01";
        $endDate   = date('Y-m-t', strtotime($startDate)); // last day of month

        $this->info("Rolling up watch time for {$period} ({$startDate} → {$endDate})...");

        // Aggregate daily totals per channel for the period
        $rows = DB::table('watch_time_daily')
            ->selectRaw('channel_id, SUM(total_seconds) as total_seconds')
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('channel_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No watch time data found for {$period}.");
            return self::SUCCESS;
        }

        $upsertData = $rows->map(fn ($row) => [
            'channel_id'     => $row->channel_id,
            'billing_period' => $period,
            'total_seconds'  => $row->total_seconds,
            'total_hours'    => round($row->total_seconds / 3600, 4),
            'created_at'     => now(),
            'updated_at'     => now(),
        ])->toArray();

        WatchTimeMonthly::upsert(
            $upsertData,
            ['channel_id', 'billing_period'],
            ['total_seconds', 'total_hours', 'updated_at']
        );

        $this->info("Done. {$rows->count()} channel(s) rolled up for {$period}.");

        return self::SUCCESS;
    }
}
