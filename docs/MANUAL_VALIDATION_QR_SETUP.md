# Manual identity validation – QR payment setup

This explains how to enable **“Pagar con QR”** for manual identity validation. **You do not need a physical POS device.** Mercado Pago’s “QR Code” product uses **logical** Stores and POS (Points of Sale) in their system for reconciliation; no hardware is required.

## Request we make and official docs

When the user taps “Pagar con QR”, the backend calls Mercado Pago’s **Orders API** to create a dynamic QR order:

- **Method / URL:** `POST https://api.mercadopago.com/v1/orders`
- **Headers:** `Authorization: Bearer {access_token}`, `X-Idempotency-Key: {unique}`, `Content-Type: application/json`
- **Body:** `type: "qr"`, `total_amount`, `external_reference`, `config.qr.external_pos_id` (from `MERCADO_PAGO_QR_PAYMENT_POS_EXTERNAL_ID`), `config.qr.mode: "dynamic"`, `transactions.payments`, `items`, `expiration_time` (e.g. PT15M).

**Official Mercado Pago docs:**

| Topic | Link |
|-------|------|
| **Create order (QR)** – request/response and `external_pos_id` | [Create order - QR Code (POST /v1/orders)](https://www.mercadopago.com.ar/developers/en/reference/in-person-payments/qr-code/orders/create-order/post) |
| **Search POS** – list POS by `external_id` or store | [Search POS (GET /pos)](https://www.mercadopago.com.ar/developers/en/reference/pos/_pos/get) |
| **Stores and POS** – concepts and setup | [Stores and POS - QR Code](https://www.mercadopago.com.ar/developers/en/docs/qr-code/stores-pos/stores-and-pos) |

If you get **`pos_not_found`** for `config.qr.external_pos_id` even though GET /pos returns your POS with the same token, the Create Order (QR) reference above is the place to confirm the expected value; you can also open a ticket with [Mercado Pago Support](https://www.mercadopago.com.ar/developers/en/support/center) and reference that endpoint.

## 1. Enable QR in your app

In `.env`:

```env
IDENTITY_VALIDATION_MANUAL_ENABLED=true
IDENTITY_VALIDATION_MANUAL_QR_ENABLED=true
MANUAL_IDENTITY_VALIDATION_COST_CENTS=1500
# QR payments use a different MP app (account that owns the POS)
MERCADO_PAGO_QR_PAYMENT_ACCESS_TOKEN=<access token for the QR payment app>
MERCADO_PAGO_QR_PAYMENT_CLIENT_ID=<client id of the QR payment app>
MERCADO_PAGO_QR_PAYMENT_CLIENT_SECRET=<client secret of the QR payment app>
MERCADO_PAGO_QR_PAYMENT_POS_EXTERNAL_ID=<see step 2>
```

- `IDENTITY_VALIDATION_MANUAL_QR_ENABLED=true` turns on the “Pagar con QR” option in the frontend.
- `MERCADO_PAGO_QR_PAYMENT_*` are the credentials of the **separate Mercado Pago app** used for QR orders (Access Token, Client ID, Client Secret). This app / account owns the Store and POS. Can be different from `MERCADO_PAGO_ACCESS_TOKEN` / `MERCADO_PAGO_CLIENT_ID` etc. used for Checkout Pro and webhooks.
- `MERCADO_PAGO_QR_PAYMENT_POS_EXTERNAL_ID` must be the **external_id** of a POS you create in Mercado Pago (step 2), using that same QR payment app.
- Mercado Pago’s QR Orders API requires **amount >= 15.00**. Set `MANUAL_IDENTITY_VALIDATION_COST_CENTS` to at least **1500** (15.00).

## 2. Where to get `external_pos_id` (no physical POS)

You need to create **one Store** and **one POS** in Mercado Pago via their API. The POS is a logical “cash register” used to group QR orders; it is **not** a physical device.

### Step A: Get your Mercado Pago User ID (for the QR account)

- In [Mercado Pago Developers](https://www.mercadopago.com.ar/developers/) → Your integration → Application details (for the account you will use for QR).
- Or from the [Users API](https://www.mercadopago.com.ar/developers/en/reference/users/_users_id/get): `GET https://api.mercadopago.com/users/me` with your **QR Payment Access Token** (the same token you set as `MERCADO_PAGO_QR_PAYMENT_ACCESS_TOKEN`).

### Step B: Create a Store (once)

```bash
curl -X POST 'https://api.mercadopago.com/users/YOUR_USER_ID/stores' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_QR_ACCESS_TOKEN' \
  -d '{
    "name": "Carpoolear Validación Manual",
    "external_id": "CARPOOLEAR_MANUAL_VALIDATION",
    "location": {
      "street_number": "123",
      "street_name": "Av. Example",
      "city_name": "Buenos Aires",
      "state_name": "Buenos Aires",
      "latitude": -34.6037,
      "longitude": -58.3816
    }
  }'
```

Save the `id` from the response (numeric Store ID). You need it for the POS.

### Step C: Create a POS (once)

```bash
curl -X POST 'https://api.mercadopago.com/pos' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_QR_ACCESS_TOKEN' \
  -d '{
    "name": "Validación manual identidad",
    "fixed_amount": true,
    "store_id": STORE_ID_FROM_STEP_B,
    "external_store_id": "CARPOOLEAR_MANUAL_VALIDATION",
    "external_id": "CARPOOLEAR_MANUAL_VALIDATION_POS"
  }'
```

- `store_id`: the numeric `id` returned when you created the store.
- `external_store_id`: must match the store’s `external_id` (e.g. `CARPOOLEAR_MANUAL_VALIDATION`).
- `external_id`: **this is the value you put in `.env`** as `MERCADO_PAGO_QR_PAYMENT_POS_EXTERNAL_ID` (e.g. `CARPOOLEAR_MANUAL_VALIDATION_POS`). You can choose any unique string (e.g. `CARPOOLEAR_MANUAL_VALIDATION_POS`).

### Step D: Configure `.env`

```env
# Use POS external_id (e.g. carpoolearmanualvalidationpos1) or numeric id as string if pos_not_found (e.g. 125096620)
MERCADO_PAGO_QR_PAYMENT_POS_EXTERNAL_ID=carpoolearmanualvalidationpos1
```

Use the POS **`external_id`** from the create response (e.g. `carpoolearmanualvalidationpos1`). If you get **`pos_not_found`**: (1) Ensure your QR payment app's Access Token is from the **same** Mercado Pago account that created the POS (check POS `user_id`). (2) Try the numeric **id** as string: `MERCADO_PAGO_QR_PAYMENT_POS_EXTERNAL_ID=125096620`. **List POS:** `GET https://api.mercadopago.com/pos?external_id=...` with `MERCADO_PAGO_QR_PAYMENT_ACCESS_TOKEN` — see [Search POS](https://www.mercadopago.com.ar/developers/en/reference/pos/_pos/get).

## 3. Summary

| Question | Answer |
|----------|--------|
| Do I need a physical POS? | **No.** Store and POS are logical entities in Mercado Pago. |
| Where does `external_pos_id` come from? | You **choose** it when creating the POS (field `external_id`). Then you set that same value in `MERCADO_PAGO_QR_PAYMENT_POS_EXTERNAL_ID`. |
| Create Store/POS once or per payment? | **Once.** One Store and one POS are enough for all manual validation QR payments. |
| Same credentials as Checkout Pro? | No. QR uses a different app: **MERCADO_PAGO_QR_PAYMENT_ACCESS_TOKEN**, **MERCADO_PAGO_QR_PAYMENT_CLIENT_ID**, **MERCADO_PAGO_QR_PAYMENT_CLIENT_SECRET**. Checkout Pro and webhooks use **MERCADO_PAGO_ACCESS_TOKEN** (and its Client ID/Secret). |

After this, the frontend will show “Pagar con QR” when manual validation is enabled and the user has not yet paid. The user scans the QR with the Mercado Pago app; when the payment is approved, the webhook marks the request as paid and the user can upload documents.
