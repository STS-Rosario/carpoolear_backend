<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\Controllers\Controller;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use STS\Services\MercadoPagoOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MercadoPagoOAuthController extends Controller
{
    /**
     * OAuth callback: MP redirects here with ?code=...&state=...
     */
    public function callback(Request $request, MercadoPagoOAuthService $oauthService)
    {
        // log the entire request payload

        \Log::info('MercadoPago OAuth callback request', ['request' => $request->all()]);

        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error) {
            \Log::warning('MercadoPago OAuth callback: MP error param', ['error' => $error]);
            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        if (!$code || !$state) {
            \Log::warning('MercadoPago OAuth callback: missing code or state', ['has_code' => !empty($code), 'has_state' => !empty($state)]);
            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        $cacheKey = 'mp_oauth_state:' . $state;
        $cached = Cache::get($cacheKey);
        Cache::forget($cacheKey);

        if (!$cached || !isset($cached['user_id'])) {
            \Log::warning('MercadoPago OAuth callback: invalid or expired state', ['state' => $state]);
            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        $userId = (int) $cached['user_id'];
        $codeVerifier = $cached['code_verifier'] ?? null;
        $user = User::find($userId);

        if (!$user) {
            \Log::warning('MercadoPago OAuth callback: user not found', ['user_id' => $userId]);
            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        try {
            $tokenResponse = $oauthService->exchangeCodeForToken($code, $codeVerifier);
            $accessToken = $tokenResponse['access_token'] ?? null;
            if (!$accessToken) {
                \Log::warning('MercadoPago OAuth callback: no access_token in token response', ['user_id' => $userId]);
                return redirect($oauthService->getFrontendRedirectUrl('error'));
            }

            $me = $oauthService->getUserMe($accessToken);
            \Log::info('MercadoPago OAuth users/me response', ['user_id' => $userId, 'me' => $me]);

            // Name comparison: our user name must include MP first_name and last_name (avoids false positives when user omits middle name)
            if (!MercadoPagoOAuthService::nameMatches($me, $user->name ?? '')) {
                $user->identity_validated = false;
                $user->identity_validated_at = null;
                $user->identity_validation_type = null;
                $user->identity_validation_rejected_at = now();
                $user->identity_validation_reject_reason = 'name_mismatch';
                $user->save();
                MercadoPagoRejectedValidation::create([
                    'user_id' => $user->id,
                    'reject_reason' => 'name_mismatch',
                    'mp_payload' => MercadoPagoOAuthService::filterMePayloadForStorage($me),
                ]);
                \Log::info('MercadoPago OAuth callback: name mismatch', ['user_id' => $userId]);
                return redirect($oauthService->getFrontendRedirectUrl('name_mismatch'));
            }

            $identification = $me['identification'] ?? null;
            if (!$identification || !isset($identification['number'])) {
                \Log::warning('MercadoPago OAuth callback: no identification in users/me', ['user_id' => $userId]);
                return redirect($oauthService->getFrontendRedirectUrl('error'));
            }

            $mpDni = MercadoPagoOAuthService::extractDniForComparison($identification);
            $userDni = MercadoPagoOAuthService::normalizeDni($user->nro_doc);

            if ($userDni === '' || $mpDni === '' || $mpDni !== $userDni) {
                $user->identity_validated = false;
                $user->identity_validated_at = null;
                $user->identity_validation_type = null;
                $user->identity_validation_rejected_at = now();
                $user->identity_validation_reject_reason = 'dni_mismatch';
                $user->save();
                MercadoPagoRejectedValidation::create([
                    'user_id' => $user->id,
                    'reject_reason' => 'dni_mismatch',
                    'mp_payload' => MercadoPagoOAuthService::filterMePayloadForStorage($me),
                ]);
                \Log::info('MercadoPago OAuth callback: DNI mismatch', [
                    'user_id' => $userId,
                    'user_dni_normalized' => $userDni,
                    'mp_dni_normalized' => $mpDni,
                ]);
                return redirect($oauthService->getFrontendRedirectUrl('dni_mismatch'));
            }

            $user->identity_validated = true;
            $user->identity_validated_at = now();
            $user->identity_validation_type = 'mercado_pago';
            $user->identity_validation_rejected_at = null;
            $user->identity_validation_reject_reason = null;
            $user->save();
            \Log::info('MercadoPago OAuth callback: success', ['user_id' => $userId]);
            return redirect($oauthService->getFrontendRedirectUrl('success'));
        } catch (\Exception $e) {
            \Log::error('MercadoPago OAuth callback exception', ['message' => $e->getMessage()]);
            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }
    }
}
