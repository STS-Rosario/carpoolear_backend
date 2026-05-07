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
        $url = $authBase.'/authorization?'.http_build_query($params);

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
     * @param  string  $code  Authorization code from redirect
     * @param  string|null  $codeVerifier  PKCE code_verifier (required if app has PKCE enabled)
     * @return array{access_token?: string, refresh_token?: string} Response from MP
     *
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

        if (! $response->successful()) {
            Log::error('MercadoPago OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to exchange code for token');
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * Get current user info from Mercado Pago (using user's access token).
     * Uses api.mercadopago.com/users/me as per plan.
     *
     * @return array identification.type, identification.number, etc.
     *
     * @throws \Exception
     */
    public function getUserMe(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://api.mercadopago.com/users/me');

        if (! $response->successful()) {
            Log::error('MercadoPago users/me failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to get user info from Mercado Pago');
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
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
     * @param  array  $identification  Must have 'type' and 'number' (type can be missing, defaults to DNI behaviour).
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
            $digits = preg_replace('/\D/', '', $number) ?? '';
            if (strlen($digits) <= 3) {
                return '';
            }
            // CUIL/CUIT: strip type + check digits (11) and derive the 8-digit block
            $number = substr($digits, 2, -1);
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
        if (extension_loaded('intl') && class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                $s = preg_replace('/\p{M}/u', '', $decomposed);
            }
        } else {
            $s = self::normalizeNameAccentFallback($s);
        }

        return $s;
    }

    /**
     * @return array<string, string>
     */
    private static function latinAccentMap(): array
    {
        return [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
        ];
    }

    private static function normalizeNameAccentFallback(string $s): string
    {
        return strtr($s, self::latinAccentMap());
    }

    /**
     * True if the Mercado Pago name part carries no letters or digits (e.g. blank, ".-", "---").
     */
    private static function isPlaceholderNamePart(string $s): bool
    {
        $s = trim($s);

        return $s !== '' && ! preg_match('/\p{L}|\p{N}/u', $s);
    }

    /**
     * Build a single comparable string from MP first_name and last_name, ignoring placeholder last names.
     */
    private static function mergeMercadoPagoDisplayName(string $firstName, string $lastName): ?string
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $firstPlaceholder = $firstName === '' || self::isPlaceholderNamePart($firstName);
        $lastPlaceholder = $lastName === '' || self::isPlaceholderNamePart($lastName);

        if ($firstPlaceholder && $lastPlaceholder) {
            return null;
        }
        if (! $firstPlaceholder && ! $lastPlaceholder) {
            return trim($firstName.' '.$lastName);
        }
        if (! $firstPlaceholder) {
            return $firstName;
        }

        // Missing first name in MP: do not fall back to last-only (too many false positives).
        return null;
    }

    /**
     * Whether two same-script name tokens are close enough (Mercado Pago typos like "Joserina"/"Josefina").
     */
    private static function nameTokensRoughlyEqual(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $la = strlen($a);
        $lb = strlen($b);
        $maxLen = max($la, $lb);
        if ($maxLen < 5 || $la > 255 || $lb > 255) {
            return false;
        }

        $lev = levenshtein($a, $b);

        return $maxLen >= 8 ? $lev <= 2 : $lev <= 1;
    }

    /**
     * True if $needle appears as a full word segment in normalized MP name (avoid "cas" ⊆ "caso").
     */
    private static function mercadoTokenAppearsAsWord(string $haystackWords, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return (bool) preg_match(
            '/(?:^|\s)'.preg_quote($needle, '/').'(?:\s|$)/u',
            $haystackWords
        );
    }

    private static function userTokenMatchesMercadoPagoComparable(string $mpComparableNorm, string $userToken): bool
    {
        if ($userToken === '') {
            return true;
        }
        if (self::mercadoTokenAppearsAsWord($mpComparableNorm, $userToken)) {
            return true;
        }

        $words = preg_split('/\s+/', $mpComparableNorm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $w) {
            if ($userToken === $w || self::nameTokensRoughlyEqual($userToken, $w)) {
                return true;
            }
        }

        for ($i = 0, $n = count($words); $i + 1 < $n; $i++) {
            if (strlen((string) $words[$i + 1]) !== 1) {
                continue;
            }
            $glue = $words[$i].$words[$i + 1];
            if ($glue !== '' && ($userToken === $glue || self::nameTokensRoughlyEqual($userToken, $glue))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if our user's name matches MP's first_name and last_name.
     * When DNI already matches, accepts: user tokens all present in the combined MP display name (with typo/glue tolerance),
     * or the prior rule (user string contains full MP last name and a first-name part). Accent- and case-insensitive.
     *
     * @param  array  $me  Full /users/me response (first_name, last_name).
     * @param  string  $userName  Our user's name field.
     * @return bool True when display names are consistent for identity validation.
     */
    public static function nameMatches(array $me, string $userName): bool
    {
        $firstName = trim((string) ($me['first_name'] ?? ''));
        $lastName = trim((string) ($me['last_name'] ?? ''));
        $name = trim((string) $userName);

        if ($name === '') {
            return false;
        }

        $mpMerged = self::mergeMercadoPagoDisplayName($firstName, $lastName);
        if ($mpMerged === null) {
            return false;
        }

        $nameNorm = self::normalizeNameForComparison($name);
        $mpNorm = self::normalizeNameForComparison($mpMerged);
        if ($nameNorm === '' || $mpNorm === '') {
            return false;
        }

        // Only MP ⊇ user as a contiguous string; the reverse would accept "Ana López" vs MP first_name "Ana" only.
        if (str_contains($mpNorm, $nameNorm)) {
            return true;
        }

        $userTokens = preg_split('/\s+/', $nameNorm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($userTokens !== []) {
            $significant = array_values(array_filter(
                $userTokens,
                static fn (string $t): bool => strlen($t) >= 2 || str_contains($mpNorm, $t)
            ));
            $allMatch = false;
            if ($significant !== []) {
                $allMatch = true;
                foreach ($significant as $t) {
                    if (! self::userTokenMatchesMercadoPagoComparable($mpNorm, $t)) {
                        $allMatch = false;

                        break;
                    }
                }
            }

            if ($allMatch) {
                return true;
            }
        }

        $firstPlaceholder = $firstName === '' || self::isPlaceholderNamePart($firstName);
        $lastPlaceholder = $lastName === '' || self::isPlaceholderNamePart($lastName);
        if ($firstPlaceholder || $lastPlaceholder) {
            return false;
        }

        $firstNorm = self::normalizeNameForComparison($firstName);
        $lastNorm = self::normalizeNameForComparison($lastName);

        if (! str_contains($nameNorm, $lastNorm)) {
            return false;
        }

        if (str_contains($nameNorm, $firstNorm)) {
            return true;
        }

        $firstWords = preg_split('/\s+/', $firstNorm, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($firstWords as $word) {
            if ($word !== '' && str_contains($nameNorm, $word)) {
                return true;
            }
        }

        return false;
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
     * Build frontend redirect URL with result and optional details.
     */
    public function getFrontendRedirectUrl(string $result, array $details = []): string
    {
        $query = array_merge(['result' => $result], $details);

        return $this->frontendRedirectBase.'/setting/identity-validation?'.http_build_query($query);
    }
}
