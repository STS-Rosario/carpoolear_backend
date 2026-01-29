<?php

namespace STS\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoOAuthService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected string $frontendRedirectBase;
    protected bool $pkceEnabled;

    public function __construct()
    {
        $this->clientId = config('services.mercadopago.client_id', '');
        $this->clientSecret = config('services.mercadopago.client_secret', '');
        $this->redirectUri = config('services.mercadopago.oauth_redirect_uri', '');
        $this->frontendRedirectBase = rtrim(config('services.mercadopago.oauth_frontend_redirect', config('app.url')), '/');
        $this->pkceEnabled = (bool) config('services.mercadopago.oauth_pkce_enabled', false);
    }

    /**
     * Build the authorization URL for the user to be redirected to Mercado Pago.
     * Per Mercado Pago docs: client_id, response_type=code, platform_id=mp, state, redirect_uri.
     * When PKCE is enabled (Application details → "authorization code flow with PKCE"), also sends code_challenge and code_challenge_method.
     *
     * @return string|array If PKCE enabled, returns ['authorization_url' => string, 'code_verifier' => string]; otherwise returns the URL string
     */
    public function getAuthorizationUrl(string $state)
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $this->redirectUri,
        ];

        $codeVerifier = null;
        if ($this->pkceEnabled) {
            $codeVerifier = $this->generateCodeVerifier();
            $params['code_challenge'] = $this->generateCodeChallenge($codeVerifier);
            $params['code_challenge_method'] = 'S256';
            // PKCE flow: docs do not include platform_id in the authorization URL
        } else {
            $params['platform_id'] = 'mp';
        }

        $authBase = rtrim(config('services.mercadopago.oauth_auth_url_base', 'https://auth.mercadopago.com'), '/');
        $url = $authBase . '/authorization?' . http_build_query($params);

        if ($this->pkceEnabled) {
            return [
                'authorization_url' => $url,
                'code_verifier' => $codeVerifier,
            ];
        }

        return $url;
    }

    /**
     * PKCE: code_verifier = random 43–128 chars (letters, numbers, -, _, ., ~).
     */
    protected function generateCodeVerifier(): string
    {
        $length = random_int(43, 64);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $verifier = '';
        for ($i = 0; $i < $length; $i++) {
            $verifier .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $verifier;
    }

    /**
     * PKCE: code_challenge = BASE64URL(SHA256(code_verifier)).
     */
    protected function generateCodeChallenge(string $codeVerifier): string
    {
        $hash = hash('sha256', $codeVerifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Exchange authorization code for access token.
     * Per Mercado Pago API reference: POST with Content-Type: application/json and body with client_id, client_secret, code, grant_type, redirect_uri; optional code_verifier when PKCE.
     *
     * @param string $code Authorization code from redirect
     * @param string|null $codeVerifier PKCE code_verifier (required if app has PKCE enabled)
     * @return array{access_token?: string, refresh_token?: string} Response from MP
     * @throws \Exception
     */
    public function exchangeCodeForToken(string $code, ?string $codeVerifier = null): array
    {
        $body = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ];
        if ($codeVerifier !== null && $codeVerifier !== '') {
            $body['code_verifier'] = $codeVerifier;
        }

        $response = Http::acceptJson()
            ->contentType('application/json')
            ->post('https://api.mercadopago.com/oauth/token', $body);

        if (!$response->successful()) {
            Log::error('MercadoPago OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for token');
        }

        return $response->json();
    }

    /**
     * Get current user info from Mercado Pago (using user's access token).
     * Uses api.mercadopago.com/users/me as per plan.
     *
     * @return array identification.type, identification.number, etc.
     * @throws \Exception
     */
    public function getUserMe(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://api.mercadopago.com/users/me');

        if (!$response->successful()) {
            Log::error('MercadoPago users/me failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to get user info from Mercado Pago');
        }

        return $response->json();
    }

    /**
     * Normalize DNI string for comparison: trim, remove dots and spaces, digits only.
     */
    public static function normalizeDni(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $normalized = preg_replace('/[\s.]/', '', $value);
        return preg_replace('/\D/', '', $normalized) ?: $normalized;
    }

    /**
     * Extract DNI from Mercado Pago identification for comparison with nro_doc.
     * - DNI: use the number as-is (normalized).
     * - CUIL/CUIT: number is longer; strip first 2 chars and last char to get the DNI part, then normalize.
     *
     * @param array $identification Must have 'type' and 'number' (type can be missing, defaults to DNI behaviour).
     * @return string Normalized DNI string to compare with user nro_doc.
     */
    public static function extractDniForComparison(array $identification): string
    {
        $number = (string) ($identification['number'] ?? '');
        $type = strtoupper(trim((string) ($identification['type'] ?? 'DNI')));

        if ($number === '') {
            return '';
        }

        if ($type === 'CUIL' || $type === 'CUIT') {
            // CUIL/CUIT: ignore first 2 and last 1 character to get the DNI part
            if (strlen($number) <= 3) {
                return '';
            }
            $number = substr($number, 2, -1);
        }

        return self::normalizeDni($number);
    }

    /**
     * Normalize string for name comparison: lowercase, collapse spaces, remove accents (e.g. González -> Gonzalez).
     */
    public static function normalizeNameForComparison(string $s): string
    {
        $s = preg_replace('/\s+/', ' ', strtolower(trim($s)));
        if ($s === '') {
            return '';
        }
        // Prefer intl Normalizer (NFD + strip combining marks) for full Unicode accent support
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                $s = preg_replace('/\p{M}/u', '', $decomposed);
            }
        } else {
            // Fallback: common Latin accents so González matches Gonzalez without intl
            $accents = [
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
                'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'œ' => 'oe',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
                'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
            ];
            $s = strtr($s, $accents);
        }
        return $s;
    }

    /**
     * Check if our user's name "includes" MP's first_name and last_name (avoids false negatives when user omits middle name).
     * MP returns first_name and last_name; we compare case-insensitively with normalized spaces and accent-insensitive (e.g. González matches Gonzalez).
     *
     * @param array $me Full /users/me response (first_name, last_name).
     * @param string $userName Our user's name field.
     * @return bool True if user name includes both MP first_name and last_name.
     */
    public static function nameMatches(array $me, string $userName): bool
    {
        $firstName = trim((string) ($me['first_name'] ?? ''));
        $lastName = trim((string) ($me['last_name'] ?? ''));
        $name = trim((string) $userName);

        if ($firstName === '' || $lastName === '') {
            return false;
        }

        $nameNorm = self::normalizeNameForComparison($name);
        $firstNorm = self::normalizeNameForComparison($firstName);
        $lastNorm = self::normalizeNameForComparison($lastName);

        return str_contains($nameNorm, $firstNorm) && str_contains($nameNorm, $lastNorm);
    }

    /**
     * Filter /users/me response to only the properties we store for rejected validations.
     * Keys: email, phone, address, first_name, last_name, country_id, identification, registration_date.
     */
    public static function filterMePayloadForStorage(array $me): array
    {
        $allowed = [
            'email',
            'phone',
            'address',
            'first_name',
            'last_name',
            'country_id',
            'identification',
            'registration_date',
        ];
        return array_intersect_key($me, array_flip($allowed));
    }

    /**
     * Build frontend redirect URL with result query param.
     */
    public function getFrontendRedirectUrl(string $result): string
    {
        return $this->frontendRedirectBase . '/setting/identity-validation?result=' . urlencode($result);
    }
}
