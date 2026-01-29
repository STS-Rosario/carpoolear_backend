# Mercado Pago OAuth – Identity validation setup

This describes what to configure in **backend** and **frontend** (and in Mercado Pago) so “Validar con Mercado Pago” works.

---

## 1. Mercado Pago Developer (application)

1. Go to [Mercado Pago Developers](https://www.mercadopago.com.ar/developers).
2. Open your app (or create one) and go to **Credenciales**.
3. For **Producción** (or **Pruebas** for testing), copy:
   - **App ID** → use as `MERCADO_PAGO_CLIENT_ID`
   - **Client Secret** → use as `MERCADO_PAGO_CLIENT_SECRET`
4. In the app, open **Configuración** / **URLs de redirección** (or similar) and add the **exact** backend callback URL (see below). Mercado Pago will only redirect to URLs you register.

---

## 2. Backend (.env)

In `carpoolear_backend/.env` set:

```env
# Mercado Pago OAuth (identity validation via RENAPER)
MERCADO_PAGO_CLIENT_ID=your_app_id_here
MERCADO_PAGO_CLIENT_SECRET=your_client_secret_here
MERCADO_PAGO_OAUTH_REDIRECT_URI=https://your-backend-domain.com/api/mercadopago/oauth/callback
MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT=https://your-frontend-domain.com
# Set to true if you enabled "Authorization code flow with PKCE" in Application details
MERCADO_PAGO_OAUTH_PKCE_ENABLED=false
```

- **MERCADO_PAGO_OAUTH_REDIRECT_URI**  
  - Must be the **full** URL where Mercado Pago redirects after the user authorizes. Mercado Pago requires it to be a **static URL**; it must **match exactly** what you configure in the app (no extra query params—use `state` for that).  
  - In this project the route is `GET /api/mercadopago/oauth/callback`, so:
    - Production: `https://api.carpoolear.com/api/mercadopago/oauth/callback` (replace with your real backend URL).
    - Local: `http://localhost/api/mercadopago/oauth/callback` (or whatever your backend base URL is).  
  - This **exact** URL must be added in the Mercado Pago app’s “URLs de redirección” (Application details → Redirect URLs).

- **MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT**  
  - Base URL of the **frontend** app (no trailing slash).  
  - After the backend finishes the OAuth flow it redirects the user to:  
    `{MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT}/setting/identity-validation?result=success|error|dni_mismatch`.  
  - Examples:
    - Production: `https://carpoolear.com.ar`
    - Local frontend: `http://localhost:8080` (or your dev URL).

- **MERCADO_PAGO_OAUTH_PKCE_ENABLED**  
  - Set to `true` only if in [Application details](https://www.mercadopago.com.ar/developers/en/docs/your-integrations/application-details) you enabled **“Authorization code flow with PKCE”**. If that option is enabled and this is `false`, you may see “La aplicación no está preparada para conectarse a Mercado Pago” after the user logs in.

If any of these are missing or wrong, the OAuth check will not work (redirect errors, “invalid redirect_uri”, or wrong final page).

---

## 3. “La aplicación no está preparada para conectarse a Mercado Pago”

This message usually appears **after** the user logs in to Mercado Pago. Check:

1. **Redirect URI**  
   In Application details → Redirect URLs, the URL must be **exactly** the same as `MERCADO_PAGO_OAUTH_REDIRECT_URI` (including scheme and path, no trailing slash unless you use it in .env).

2. **PKCE**  
   If in Application details you turned on **“Authorization code flow with PKCE”**, set `MERCADO_PAGO_OAUTH_PKCE_ENABLED=true` in `.env`. The backend sends `code_challenge` / `code_challenge_method` and omits `platform_id` when PKCE is enabled (per MP docs). If you still get **400 Bad Request**, try: `MERCADO_PAGO_OAUTH_AUTH_URL_BASE=https://auth.mercadopago.com.ar`

3. **Token request**  
   The backend sends the token exchange as **JSON** (`Content-Type: application/json`) as per the [Mercado Pago OAuth API](https://www.mercadopago.com.ar/developers/en/docs/security/oauth/creation).

---

## 4. Frontend

No extra env vars are required in the frontend for OAuth itself.

- The **identity validation** page must be reachable at:  
  `{MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT}/setting/identity-validation`  
  (this is where the backend sends the user with `?result=success|error|dni_mismatch`).
- The frontend calls `GET /api/users/mercadopago-oauth-url` (with JWT). The backend returns `authorization_url`; the app redirects the user to that URL to start the flow.
- The **OAuth button** is disabled when the user has no DNI (`nro_doc`) in their profile; the user must set DNI first.

So for the OAuth check to work you only need:

1. Backend .env set as above (and backend running on the URL used in `MERCADO_PAGO_OAUTH_REDIRECT_URI`).
2. Mercado Pago app configured with that same redirect URI.
3. Frontend reachable at the URL set in `MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT` and with the route `/setting/identity-validation`.

---

## 5. Quick checklist

| Step | Where | What |
|------|--------|------|
| 1 | Mercado Pago Developers | Create/get app, copy **App ID** and **Client Secret**. |
| 2 | Mercado Pago app | Add redirect URL = `https://your-backend.com/api/mercadopago/oauth/callback`. |
| 3 | Backend `.env` | Set `MERCADO_PAGO_CLIENT_ID`, `MERCADO_PAGO_CLIENT_SECRET`, `MERCADO_PAGO_OAUTH_REDIRECT_URI`, `MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT`. |
| 4 | Frontend | Ensure `/setting/identity-validation` exists and matches `MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT`. |

---

## 6. When OAuth is “disabled”

If you leave `MERCADO_PAGO_CLIENT_ID` (and secret) empty:

- The backend still builds an authorization URL but with empty `client_id`; Mercado Pago will reject it.
- So effectively OAuth is “off” until you set the env vars and the redirect URL in the MP app.

To avoid sending users to a broken flow, you can:

- Have the backend check that `client_id` (and optionally `client_secret`) are non-empty before returning `authorization_url`, and return a clear error (e.g. 503 or 400) when OAuth is not configured.
- Optionally expose something like `mercadopago_oauth_available` in the public config so the frontend can hide or disable the “Validar con Mercado Pago” button when OAuth is not set up.
