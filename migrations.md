# Database Migrations — Streaming Platform

All migrations use UUID primary keys and PostgreSQL-compatible syntax.
Run in the order listed.

---

## 001 — users

```php
<?php
// database/migrations/2024_01_01_000001_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['subscriber', 'provider', 'admin'])->default('subscriber');
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

---

## 002 — subscriber_profiles

```php
<?php
// database/migrations/2024_01_01_000002_create_subscriber_profiles_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriber_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('subscription_status', ['active', 'expired', 'cancelled', 'trial'])
                  ->default('trial');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('paychangu_customer_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('subscription_status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_profiles');
    }
};
```

---

## 003 — provider_profiles

```php
<?php
// database/migrations/2024_01_01_000003_create_provider_profiles_table.php

return new class extends Migration {
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
```

---

## 004 — subscription_plans

```php
<?php
// database/migrations/2024_01_01_000004_create_subscription_plans_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_mwk', 12, 2);
            $table->integer('duration_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
```

---

## 005 — subscriptions

```php
<?php
// database/migrations/2024_01_01_000005_create_subscriptions_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('subscriber_id')
                  ->references('id')->on('subscriber_profiles')
                  ->cascadeOnDelete();
            $table->foreignUuid('plan_id')
                  ->references('id')->on('subscription_plans');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('paychangu_ref')->nullable()->unique();
            $table->timestamps();

            $table->index('subscriber_id');
            $table->index('status');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
```

---

## 006 — payments

```php
<?php
// database/migrations/2024_01_01_000006_create_payments_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscription_id')
                  ->references('id')->on('subscriptions')
                  ->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('MWK');
            $table->string('paychangu_tx_id')->nullable()->unique();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['mobile_money', 'card', 'bank'])->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('paychangu_tx_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

---

## 007 — channels

```php
<?php
// database/migrations/2024_01_01_000007_create_channels_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('provider_id')
                  ->references('id')->on('provider_profiles')
                  ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('category')->nullable();
            $table->string('language', 10)->default('en');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_live')->default(false);
            // RTMP credentials for live streaming
            $table->string('rtmp_key')->unique()->nullable();
            $table->string('rtmp_ingest_url')->nullable();
            $table->string('hls_playback_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_id');
            $table->index('slug');
            $table->index('is_active');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
```

---

## 008 — content_items

```php
<?php
// database/migrations/2024_01_01_000008_create_content_items_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['vod', 'live_recording', 'short'])->default('vod');
            $table->string('file_path')->nullable();
            $table->string('hls_url')->nullable();
            $table->string('dash_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->bigInteger('file_size_bytes')->default(0);
            $table->enum('status', ['uploading', 'processing', 'ready', 'failed'])->default('uploading');
            $table->string('resolution')->nullable();
            $table->json('qualities')->nullable(); // e.g. ["360p","720p","1080p"]
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('channel_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};
```

---

## 009 — watch_sessions

```php
<?php
// database/migrations/2024_01_01_000009_create_watch_sessions_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watch_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('content_id')
                  ->nullable()
                  ->references('id')->on('content_items')
                  ->nullOnDelete();
            $table->enum('content_type', ['live', 'vod'])->default('vod');
            $table->integer('total_seconds')->default(0);
            $table->integer('last_position')->default(0); // playback position in seconds
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->enum('platform', ['android', 'ios', 'web', 'tv'])->nullable();
            $table->string('quality')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('channel_id');
            $table->index('content_id');
            $table->index('is_active');
            $table->index(['channel_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_sessions');
    }
};
```

---

## 010 — watch_time_daily

```php
<?php
// database/migrations/2024_01_01_000010_create_watch_time_daily_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watch_time_daily', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('total_seconds')->default(0);
            $table->timestamps();

            $table->unique(['channel_id', 'user_id', 'date']);
            $table->index(['channel_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_time_daily');
    }
};
```

---

## 011 — watch_time_monthly

```php
<?php
// database/migrations/2024_01_01_000011_create_watch_time_monthly_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watch_time_monthly', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->string('billing_period', 7); // e.g. "2024-06"
            $table->integer('total_seconds')->default(0);
            $table->decimal('total_hours', 10, 4)->default(0);
            $table->timestamps();

            $table->unique(['channel_id', 'billing_period']);
            $table->index('billing_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_time_monthly');
    }
};
```

---

## 012 — billing_periods

```php
<?php
// database/migrations/2024_01_01_000012_create_billing_periods_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('period_label', 7)->unique(); // e.g. "2024-06"
            $table->date('starts_at');
            $table->date('ends_at');
            $table->integer('total_subscribers')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('platform_share', 14, 2)->default(0);   // 20%
            $table->decimal('provider_pool', 14, 2)->default(0);    // 80%
            $table->decimal('platform_percentage', 5, 2)->default(20.00);
            $table->enum('status', ['open', 'calculating', 'closed', 'paid'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
```

---

## 013 — provider_payouts

```php
<?php
// database/migrations/2024_01_01_000013_create_provider_payouts_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('provider_id')
                  ->references('id')->on('provider_profiles')
                  ->cascadeOnDelete();
            $table->foreignUuid('billing_period_id')
                  ->references('id')->on('billing_periods')
                  ->cascadeOnDelete();
            $table->decimal('watch_seconds', 14, 0)->default(0);
            $table->decimal('watch_hours', 10, 4)->default(0);
            $table->decimal('share_percent', 8, 6)->default(0);  // e.g. 0.174000
            $table->decimal('amount_mwk', 14, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'paid', 'failed'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'billing_period_id']);
            $table->index('status');
            $table->index('billing_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payouts');
    }
};
```

---

## 014 — watch_anomalies

```php
<?php
// database/migrations/2024_01_01_000014_create_watch_anomalies_table.php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watch_anomalies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('session_id')
                  ->references('id')->on('watch_sessions')
                  ->cascadeOnDelete();
            $table->integer('position_delta')->default(0);
            $table->integer('actual_elapsed')->default(0);
            $table->integer('discrepancy_seconds')->default(0);
            $table->enum('type', ['skip', 'fast_forward', 'abnormal_session'])->default('skip');
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index('session_id');
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_anomalies');
    }
};
```

---

## Revenue Calculation Logic (Service Class)

```php
<?php
// app/Services/RevenueCalculationService.php

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\WatchTimeMonthly;
use App\Models\ProviderPayout;

class RevenueCalculationService
{
    public function calculate(BillingPeriod $period): void
    {
        // 1. Get total watch time across all channels for this period
        $allWatchTime = WatchTimeMonthly::where('billing_period', $period->period_label)
            ->get();

        $totalSeconds = $allWatchTime->sum('total_seconds');

        if ($totalSeconds === 0) return; // nothing to distribute

        // 2. For each channel, calculate share and payout
        foreach ($allWatchTime as $channelWatch) {
            $sharePercent = $channelWatch->total_seconds / $totalSeconds;
            $amount = round($period->provider_pool * $sharePercent, 2);

            ProviderPayout::updateOrCreate(
                [
                    'provider_id'       => $channelWatch->channel->provider_id,
                    'billing_period_id' => $period->id,
                ],
                [
                    'watch_seconds'  => $channelWatch->total_seconds,
                    'watch_hours'    => round($channelWatch->total_seconds / 3600, 4),
                    'share_percent'  => $sharePercent,
                    'amount_mwk'     => $amount,
                    'status'         => 'pending',
                ]
            );
        }

        $period->update(['status' => 'calculating']);
    }
}
```

---

## Heartbeat Aggregation Job

```php
<?php
// app/Jobs/AggregateHeartbeatsJob.php

namespace App\Jobs;

use App\Models\WatchSession;
use App\Models\WatchTimeDaily;
use Illuminate\Support\Facades\Redis;

class AggregateHeartbeatsJob implements ShouldQueue
{
    public function handle(): void
    {
        $heartbeats = [];

        // Drain the Redis queue in batches of 500
        while ($raw = Redis::lpop('heartbeats:queue')) {
            $heartbeats[] = json_decode($raw, true);
            if (count($heartbeats) >= 500) break;
        }

        foreach ($heartbeats as $hb) {
            // Update the live session
            WatchSession::where('id', $hb['session_id'])
                ->where('user_id', $hb['viewer_id'])
                ->update([
                    'total_seconds'      => DB::raw("total_seconds + {$hb['actual_elapsed_seconds']}"),
                    'last_heartbeat_at'  => $hb['timestamp'],
                    'last_position'      => $hb['playback_position'],
                ]);

            // Upsert into daily aggregate
            WatchTimeDaily::upsert(
                [
                    'channel_id'    => $hb['channel_id'],
                    'user_id'       => $hb['viewer_id'],
                    'date'          => now()->toDateString(),
                    'total_seconds' => $hb['actual_elapsed_seconds'],
                ],
                ['channel_id', 'user_id', 'date'],
                ['total_seconds' => DB::raw('watch_time_daily.total_seconds + excluded.total_seconds')]
            );
        }
    }
}
```
