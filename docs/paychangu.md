# PayChangu — subscriber payments

Subscribers buy plans with a PayChangu **direct mobile-money charge**
(Airtel Money / TNM Mpamba). The app-facing contract is in
[mobile-app-integration.md](mobile-app-integration.md#paying-for-a-plan-mobile-money).

## Setup

1. In the PayChangu dashboard copy the **secret key** and the **webhook secret**.
2. Set them in `.env`:
   ```
   PAYCHANGU_SECRET_KEY=sec-live-…
   PAYCHANGU_WEBHOOK_SECRET=…
   ```
3. Set the dashboard webhook URL to `https://<APP_URL>/webhooks/paychangu`.
4. `php artisan migrate && php artisan optimize` (config + route caches), and
   make sure a queue worker is running — the webhook confirms payments on the queue.

## Flow

```
App → POST /api/subscription/checkout
  → SubscriptionPaymentService::startCheckout()
      creates Subscription(pending) + Payment(pending), charge_id = THR-…
      POST /mobile-money/payments/initialize  → PIN prompt on the phone
PayChangu → POST /webhooks/paychangu  (HMAC-SHA256 of raw body in "Signature")
  → ConfirmPayChanguPaymentJob → SubscriptionPaymentService::confirm()
App → GET /api/subscription/checkout/{reference}  (fallback, verifies at most every 10 s)
  → SubscriptionPaymentService::confirm()

confirm(): GET /mobile-money/payments/{charge_id}/verify
  success + amount ≥ plan price + MWK → Payment completed,
      SubscriptionActivationService::activate(), Payment::assignToBillingPeriod()
  failed/cancelled/…                   → Payment failed, Subscription failed
  anything else                         → still pending
```

The webhook body is only used to find the charge. Access is granted only after
the verify call, so a forged or replayed webhook cannot activate a
subscription. `confirm()` locks the payment row and is idempotent.

`payments.paychangu_tx_id` and `subscriptions.paychangu_ref` both hold our
`charge_id`.

## Unverified against PayChangu's docs

The PayChangu developer site blocks automated reads, so these were taken from
search snippets and a third-party SDK. Check them with a sandbox key before going live:

- **Phone format.** `SubscriptionPaymentService::normalizePhone()` sends the
  local 10-digit form (`0991234567`).
- **Status strings.** `confirm()` treats `success`, `successful` and
  `completed` as paid, and `failed`, `cancelled`, `declined`, `expired` and
  `reversed` as failed. Anything else is still pending.
- **Response shape.** The charge record is read from `data` (or
  `data.transaction`) with `status`, `amount` and `currency`.
- **Webhook payload.** The charge id is read from `charge_id`,
  `data.charge_id`, `reference` or `tx_ref`.
