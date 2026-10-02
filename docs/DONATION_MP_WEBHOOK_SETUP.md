# Platform Donations — Mercado Pago Webhook Setup

Platform donations reuse the **main** Carpoolear Mercado Pago application (`MERCADO_PAGO_ACCESS_TOKEN`).

Club Carpoolear membership is granted only from an **authorized subscription** (webhook or explicit reconcile). The welcome return URL (`/app/club-carpoolear/welcome?result=success`) is a `back_url`, not membership.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Backend origin used as `notification_url` (production: `https://carpoolear.com.ar`) |
| `MERCADO_PAGO_ACCESS_TOKEN` | Preferences, payments, preapprovals |
| `MERCADO_PAGO_WEBHOOK_SECRET` | `x-signature` verification |
| `MERCADO_PAGO_REFERENCE_SALT` | Hashed `external_reference` |
| `PLATFORM_DONATIONS_API_ENABLED` | Feature flag (`true` to enable checkout API) |
| `FRONTEND_URL` | Success/failure redirects after checkout |

## Donation tiers (required for monthly checkout)

`GET /api/donation-tiers` must return cafe / beer / food. If `donation_tiers` is empty, `POST /api/donations/checkout/monthly` 404s in `resolveTier()`.

Production needs:

```bash
php artisan db:seed --class=DonationTierSeeder
```

The seeder is idempotent (`updateOrCreate` by slug) and is also called from `DatabaseSeeder`. Amounts are ARS × 100 in `amount_cents` (5000 / 7500 / 12000 ARS).

## Checkout must create a pending row

Monthly Club subscribe should go through **`POST /api/donations/checkout/monthly`**. That endpoint:

1. Resolves an active donation tier.
2. Creates a **pending** `donation_subscriptions` row and hashed `external_reference`.
3. Redirects to hosted checkout (`/subscriptions/checkout?preapproval_plan_id=&external_reference=`).

Webhooks then update that row and call `ClubCarpoolearMembershipService::applyAuthorizedMembership()`.

If the client opens a raw Mercado Pago plan URL instead, a late `subscription_preapproval` webhook can still create the row when `external_reference` decodes, but checkout-pending + hashed reference is the intended path.

`resources/views/aportar.blade.php` still has hardcoded `linksMensual` plan IDs. Those bypass Club tables. Legacy `POST /api/users/donation` only writes the old `donations` table.

## Subscriptions: `notification_url` is required

The Mercado Pago Developers panel method (**Your integrations → Webhooks**, including **Planes y suscripciones**) does **not** subscribe you to subscription topics. Mercado Pago documents that panel checkboxes are **not available for Subscriptions**.

You must set `notification_url` when creating the **plan** (and/or the preapproval). This backend sends:

```text
{APP_URL}/webhooks/mercadopago?source_news=webhooks
```

on preapproval **plan** creation (`MercadoPagoService::buildPreapprovalPlanRequest`). Production `APP_URL` is `https://carpoolear.com.ar`.

The panel can still be used for **payment** topics and to copy the webhook secret. Subscription events (`subscription_preapproval`, `subscription_authorized_payment`) will not appear unless `notification_url` was set on the plan/subscription.

## Query shape (`data.id`)

Production Mercado Pago POSTs `/webhooks/mercadopago?data.id=...&type=payment` (and the same `data.id` query for subscriptions). The backend verifies HMAC using the query id Mercado Pago signed:

- `data.id` (production)
- `data_id` (legacy / PHP `parse_str` rewrite of `data.id`)

Body `data.id` / `data_id` is only a fallback for **resource lookup**, not for the signature manifest.

Incoming webhooks are logged at info with `type`, `action`, and `data.id` (no secrets).

## Mercado Pago Developers panel (payments + secret)

1. Open [Mercado Pago Developers](https://www.mercadopago.com.ar/developers/panel/app) → your app.
2. **Webhooks** → Production (and Test for staging):
   - URL: `https://carpoolear.com.ar/webhooks/mercadopago`
   - Enable **payment** if you use Checkout Pro / one-time donations.
   - Do **not** expect **Planes y suscripciones** checkboxes to deliver `subscription_preapproval`.
3. Copy the **webhook secret** into `MERCADO_PAGO_WEBHOOK_SECRET`.
4. Confirm **Subscriptions / Preapproval** product is enabled for Argentina.

## Sandbox testing

1. Use test credentials in `.env` for staging.
2. Complete checkout via `POST /api/donations/checkout/monthly` (not a raw plan URL).
3. Verify logs for `MercadoPago webhook received` and `Donación Plataforma` external references.
4. Confirm a `donation_subscriptions` row, `users.monthly_donate`, and `club_carpoolear_joined_at`.

## What the backend handles

| Webhook | Action |
|---------|--------|
| `payment.created` / `payment.updated` | One-time platform donations (`Donación Plataforma`) |
| `subscription_preapproval` (`action` is `created` / `updated`) | Subscription authorized / paused / cancelled; creates a Club row if none exists |
| `subscription_authorized_payment` | Each monthly charge |
