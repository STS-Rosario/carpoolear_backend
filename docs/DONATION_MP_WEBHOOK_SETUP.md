# Platform Donations — Mercado Pago Webhook Setup

Platform donations reuse the **main** Carpoolear Mercado Pago application (`MERCADO_PAGO_ACCESS_TOKEN`).

## Environment variables

| Variable | Purpose |
|----------|---------|
| `MERCADO_PAGO_ACCESS_TOKEN` | Preferences, payments, preapprovals |
| `MERCADO_PAGO_WEBHOOK_SECRET` | `x-signature` verification |
| `MERCADO_PAGO_REFERENCE_SALT` | Hashed `external_reference` |
| `PLATFORM_DONATIONS_API_ENABLED` | Feature flag (`true` to enable checkout API) |
| `FRONTEND_URL` | Success/failure redirects after checkout |

## Mercado Pago Developers panel

1. Open [Mercado Pago Developers](https://www.mercadopago.com.ar/developers/panel/app) → your app.
2. **Webhooks** → Production (and Test for staging):
   - URL: `https://carpoolear.com.ar/webhooks/mercadopago`
   - Enable topics:
     - `payment` (already used)
     - `subscription_preapproval`
     - `subscription_authorized_payment`
3. Copy the **webhook secret** into `MERCADO_PAGO_WEBHOOK_SECRET`.
4. Confirm **Subscriptions / Preapproval** product is enabled for Argentina.

## Sandbox testing

1. Use test credentials in `.env` for staging.
2. Simulate webhooks from the MP panel or complete a test checkout.
3. Verify logs for `Donación Plataforma` external references.

## What the backend handles

| Webhook | Action |
|---------|--------|
| `payment.created` / `payment.updated` | One-time platform donations (`Donación Plataforma`) |
| `subscription_preapproval` | Subscription authorized / paused / cancelled |
| `subscription_authorized_payment` | Each monthly charge |
