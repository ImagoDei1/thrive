# Thrive — Claude Code Context

## Project
Subscription-based streaming aggregation platform. Content providers (churches, broadcasters, NGOs, schools) stream to the platform. Subscribers pay monthly to access all content. Revenue is split 80% to providers (by watch time share) / 20% to platform.

## Stack
- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Livewire 3 + MaryUI v2 (daisyUI + Tailwind v4)
- **Database:** PostgreSQL (UUID PKs via `gen_random_uuid()`)
- **Cache/Queue:** Redis
- **Streaming:** MediaMTX + FFmpeg (RTMP ingest → HLS/DASH output)
- **Payments:** PayChangu (Malawi — MTN/Airtel mobile money)
- **Mobile/Web viewer app:** Flutter (separate repo)

## Bash Commands
```
composer run dev          # starts Laravel + Vite + queue worker + logs
php artisan serve         # Laravel only
npm run dev               # Vite only
php artisan queue:work    # queue worker
php artisan migrate       # run migrations
php artisan migrate:fresh --seed  # reset DB with seed data
php artisan test          # run Pest tests
php artisan route:list    # list all routes
php artisan optimize:clear # clear all caches
```

## Project Structure
```
app/
  Livewire/
    Admin/          # Admin portal components
    Provider/       # Provider portal components
  Models/           # Eloquent models
  Services/
    RevenueCalculationService.php
  Jobs/
    AggregateHeartbeatsJob.php
  Http/Middleware/
    RoleMiddleware.php

resources/views/
  components/
    admin-layout.blade.php
    provider-layout.blade.php
  livewire/
    admin/
    provider/

database/migrations/   # 14 migrations, all UUID PKs
routes/web.php         # admin.* and provider.* route groups
```

## Key Domain Rules — Never Violate These

1. **Revenue formula:** `provider_share = (provider_watch_seconds / total_watch_seconds) * provider_pool`
2. **Zero watch time = zero payout.** Never add minimum guarantees.
3. **Platform always takes 20%, providers pool 80%.** Stored in `billing_periods.platform_percentage`.
4. **Watch time credits `actual_elapsed_seconds` only — never `position_delta`.** Position delta is for anomaly detection only.
5. **Heartbeats only fire when `isPlaying && !isBuffering`** (Flutter client rule).
6. **RTMP keys are unique per channel** — regenerating disconnects the live stream.
7. **Providers must be `is_approved = true` before their channels are visible.**
8. **`billing_period` is stored as `"YYYY-MM"` string** — not a date column.

## Database Conventions
- All PKs: `uuid` using `gen_random_uuid()`
- Soft deletes on: `users`, `provider_profiles`, `channels`, `content_items`
- Use `ilike` for case-insensitive search (PostgreSQL)
- Use `upsert` with `excluded` syntax for atomic increments (heartbeat aggregation)
- Money columns: `decimal(14,2)` for MWK amounts, `decimal(8,6)` for share percentages

## User Roles
- `admin` → `/admin/*` routes → `admin-layout`
- `provider` → `/provider/*` routes → `provider-layout`
- `subscriber` → Flutter app only (no web portal)

## MaryUI Patterns
- Use `Mary\Traits\Toast` for notifications: `$this->success()`, `$this->warning()`, `$this->error()`
- Use `#[Rule(...)]` attributes on Livewire properties instead of `$rules` array
- Use `#[Title('...')]` attribute on Livewire class for page title
- Tables use `@scope('cell_xyz', $row)` for custom cell rendering
- Forms use `<x-form wire:submit="methodName">` wrapper
- Always use `wire:navigate` on internal links for SPA behaviour

## Code Style
- PHP: strict types, constructor property promotion, match expressions over switch
- No raw SQL — use Eloquent query builder
- Services in `app/Services/` for business logic (revenue calc, payout processing)
- Jobs in `app/Jobs/` for async work (heartbeat aggregation, transcoding, payouts)
- Livewire components are full-page — always end `render()` with `->layout('components.xxx-layout')`
- Use `with('user')`, `withCount('contentItems')` — never lazy load in views

## Watch Time Pipeline
```
Flutter heartbeat (every 30s, only when playing) 
  → POST /api/heartbeat 
  → Redis lpush('heartbeats:queue', payload)
  → AggregateHeartbeatsJob (drains Redis, upserts watch_time_daily)
  → Monthly cron → watch_time_monthly
  → Billing period close → RevenueCalculationService → provider_payouts
```

## PayChangu Integration
- Malawi-native payment gateway
- Supports MTN Mobile Money + Airtel Money
- Used for subscriber monthly billing only
- Provider setup fees also go through PayChangu
- Webhook endpoint must verify `paychangu_tx_id` uniqueness before activating subscription

## Testing
- Framework: Pest
- Feature tests for: revenue calculation, watch time aggregation, billing period closing, role middleware
- Run: `php artisan test` or `composer run test`

## What NOT to Do
- Do not install Filament — this project uses MaryUI only
- Do not use Alpine.js for reactivity — Livewire handles all interactivity
- Do not write migrations with MySQL-specific syntax (`AUTO_INCREMENT`, `VARCHAR` without length on PK)
- Do not add `@tailwindcss/forms` plugin — conflicts with daisyUI
- Do not put lengthy documentation in this file — link to `/docs` instead
