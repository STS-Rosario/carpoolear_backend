<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use STS\Exceptions\MercadoPagoOAuthRequestException;
use STS\Http\Controllers\Controller;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use STS\Services\IdentityVerificationOutcome;
use STS\Services\MercadoPagoOAuthService;
use STS\Services\UserIdentityVerificationSuccessService;

class MercadoPagoOAuthController extends Controller
{
    /**
     * OAuth callback: MP redirects here with ?code=...&state=...
     */
    public function callback(Request $request, MercadoPagoOAuthService $oauthService, IdentityVerificationOutcome $outcome)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error) {
            $cached = is_string($state) && $state !== '' ? Cache::pull('mp_oauth_state:'.$state) : null;
            $reason = $error === 'access_denied'
                ? IdentityVerificationOutcome::REASON_OAUTH_CANCELLED
                : IdentityVerificationOutcome::REASON_OAUTH_DENIED;
            $this->emitMpFailure($outcome, $cached, $reason, [
                'mp_error' => $error,
            ]);

            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        if (! $code || ! $state) {
            $this->emitMpFailure($outcome, null, IdentityVerificationOutcome::REASON_MISSING_CODE_OR_STATE, [
                'has_code' => ! empty($code),
                'has_state' => ! empty($state),
            ]);

            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        $cacheKey = 'mp_oauth_state:'.$state;
        $cached = Cache::pull($cacheKey);

        if (! $cached || ! isset($cached['user_id'])) {
            $this->emitMpFailure($outcome, is_array($cached) ? $cached : null, IdentityVerificationOutcome::REASON_INVALID_OR_EXPIRED_STATE, [
                'state' => $state,
            ]);

            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        $userId = (int) $cached['user_id'];
        $codeVerifier = $cached['code_verifier'] ?? null;
        $user = User::find($userId);

        if (! $user) {
            $this->emitMpFailure($outcome, $cached, IdentityVerificationOutcome::REASON_USER_NOT_FOUND, [
                'missing_user_id' => $userId,
            ], null);

            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }

        try {
            $tokenResponse = $oauthService->exchangeCodeForToken($code, $codeVerifier);
            $accessToken = $tokenResponse['access_token'] ?? null;
            if (! $accessToken) {
                $this->emitMpFailure($outcome, $cached, IdentityVerificationOutcome::REASON_MISSING_ACCESS_TOKEN, [], $user->id);

                return redirect($oauthService->getFrontendRedirectUrl('error'));
            }

            $me = $oauthService->getUserMe($accessToken);
            $userName = trim((string) ($user->name ?? ''));
            $mpName = trim((string) (($me['first_name'] ?? '').' '.($me['last_name'] ?? '')));
            $nameMismatch = ! MercadoPagoOAuthService::nameMatches($me, $userName);

            $identification = $me['identification'] ?? null;
            if (! $identification || ! isset($identification['number'])) {
                $this->emitMpFailure($outcome, $cached, IdentityVerificationOutcome::REASON_MISSING_IDENTIFICATION, [], $user->id);

                return redirect($oauthService->getFrontendRedirectUrl('missing_identification'));
            }

            $mpDni = MercadoPagoOAuthService::extractDniForComparison($identification);
            $userDni = MercadoPagoOAuthService::normalizeDni($user->nro_doc);
            $dniMismatch = $userDni === '' || $mpDni === '' || $mpDni !== $userDni;

            if ($nameMismatch || $dniMismatch) {
                $rejectReason = $this->resolveRejectReason($nameMismatch, $dniMismatch);
                $user->identity_validated = false;
                $user->identity_validated_at = null;
                $user->identity_validation_type = null;
                $user->identity_validation_rejected_at = now();
                $user->identity_validation_reject_reason = $rejectReason;
                $user->save();
                MercadoPagoRejectedValidation::create([
                    'user_id' => $user->id,
                    'reject_reason' => $rejectReason,
                    'mp_payload' => MercadoPagoOAuthService::filterMePayloadForStorage($me),
                ]);
                $this->emitMpFailure($outcome, $cached, $rejectReason, [], $user->id);
                $details = $this->buildMismatchRedirectDetails(
                    $nameMismatch,
                    $dniMismatch,
                    $userName,
                    $mpName,
                    $userDni,
                    $mpDni
                );

                return redirect($oauthService->getFrontendRedirectUrl($rejectReason, $details));
            }

            app(UserIdentityVerificationSuccessService::class)->applyVerification($user, 'mercado_pago');
            $outcome->emit(array_merge($this->cachedContext($cached), [
                'user_id' => $user->id,
                'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
                'name' => IdentityVerificationOutcome::NAME_SUCCEEDED,
            ]));

            return redirect($oauthService->getFrontendRedirectUrl('success'));
        } catch (MercadoPagoOAuthRequestException $e) {
            $this->emitMpFailure($outcome, $cached, $e->reason, [
                'http_status' => $e->httpStatus,
            ], $user->id);

            return redirect($oauthService->getFrontendRedirectUrl('error'));
        } catch (\Exception $e) {
            $this->emitMpFailure($outcome, $cached, IdentityVerificationOutcome::REASON_CALLBACK_EXCEPTION, [
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ], $user->id);

            return redirect($oauthService->getFrontendRedirectUrl('error'));
        }
    }

    /**
     * @param  array<string, mixed>|null  $cached
     * @param  array<string, mixed>  $metadata
     */
    private function emitMpFailure(
        IdentityVerificationOutcome $outcome,
        ?array $cached,
        string $reason,
        array $metadata = [],
        ?int $userId = null
    ): void {
        $resolvedUserId = $userId;
        if ($resolvedUserId === null && isset($cached['user_id']) && $reason !== IdentityVerificationOutcome::REASON_USER_NOT_FOUND) {
            $resolvedUserId = (int) $cached['user_id'];
        }

        $outcome->emit(array_merge($this->cachedContext($cached), [
            'user_id' => $resolvedUserId,
            'method' => IdentityVerificationOutcome::METHOD_MERCADO_PAGO,
            'name' => IdentityVerificationOutcome::NAME_FAILED,
            'reason' => $reason,
            'metadata' => $metadata,
        ]));
    }

    /**
     * @param  array<string, mixed>|null  $cached
     * @return array<string, mixed>
     */
    private function cachedContext(?array $cached): array
    {
        if (! is_array($cached)) {
            return [];
        }

        return [
            'attempt_id' => $cached['attempt_id'] ?? null,
            'surface' => $cached['surface'] ?? null,
            'platform' => $cached['platform'] ?? null,
            'app_version' => $cached['app_version'] ?? null,
        ];
    }

    private function resolveRejectReason(bool $nameMismatch, bool $dniMismatch): string
    {
        if ($nameMismatch && $dniMismatch) {
            return 'both_mismatch';
        }
        if ($nameMismatch) {
            return 'name_mismatch';
        }

        return 'dni_mismatch';
    }

    private function buildMismatchRedirectDetails(
        bool $nameMismatch,
        bool $dniMismatch,
        string $userName,
        string $mpName,
        string $userDni,
        string $mpDni
    ): array {
        $details = [];
        if ($nameMismatch) {
            $details['user_name'] = $userName;
            $details['mp_name'] = $mpName;
        }
        if ($dniMismatch) {
            $details['user_dni'] = $userDni;
            $details['mp_dni'] = $mpDni;
        }

        return $details;
    }
}
