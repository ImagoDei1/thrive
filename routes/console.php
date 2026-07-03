<?php

use App\Jobs\AggregateHeartbeatsJob;
use Illuminate\Support\Facades\Schedule;

// Drain Redis heartbeat queue every 5 minutes
Schedule::job(AggregateHeartbeatsJob::class)->everyFiveMinutes();

// Roll up daily watch time into monthly totals — runs hourly
Schedule::command('watchtime:rollup-monthly')->hourly();

// Close periods past cut-off, trigger payouts on payout_date — runs daily at 06:00
Schedule::command('payouts:process-scheduled')->dailyAt('06:00');

// Suspend subscribers whose 3-day grace period has expired — runs daily at 07:00
Schedule::command('subscriptions:expire')->dailyAt('07:00');

// Email subscribers expiring in 7 or 3 days — runs daily at 08:00
Schedule::command('subscriptions:notify-expiring')->dailyAt('08:00');
