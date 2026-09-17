<?php

namespace STS\Services;

use Illuminate\Support\Facades\Log;

class IdentityVerificationOutcome
{
    public const METHOD_MERCADO_PAGO = 'mercado_pago';

    public const METHOD_MANUAL = 'manual';

    public const NAME_ATTEMPT_STARTED = 'attempt_started';

    public const NAME_SUCCEEDED = 'succeeded';

    public const NAME_FAILED = 'failed';

    public const NAME_PAYMENT_STARTED = 'payment_started';

    public const NAME_PAYMENT_SUCCEEDED = 'payment_succeeded';

    public const NAME_PAYMENT_FAILED = 'payment_failed';

    public const NAME_DOCS_SUBMITTED = 'docs_submitted';

    public const NAME_INFO_REQUESTED = 'info_requested';

    public const NAME_CLOSED_AFTER_MP_SUCCESS = 'closed_after_mp_success';

    public const NAME_UPLOAD_REJECTED = 'upload_rejected';

    public const NAME_CONFIRM_MODAL_SHOWN = 'confirm_modal_shown';

    public const NAME_CONFIRM_MODAL_CANCELLED = 'confirm_modal_cancelled';

    public const REASON_OAUTH_CANCELLED = 'oauth_cancelled';

    public const REASON_OAUTH_DENIED = 'oauth_denied';

    public const REASON_MISSING_CODE_OR_STATE = 'missing_code_or_state';

    public const REASON_INVALID_OR_EXPIRED_STATE = 'invalid_or_expired_state';

    public const REASON_USER_NOT_FOUND = 'user_not_found';

    public const REASON_TOKEN_EXCHANGE_FAILED = 'token_exchange_failed';

    public const REASON_MISSING_ACCESS_TOKEN = 'missing_access_token';

    public const REASON_USERS_ME_FAILED = 'users_me_failed';

    public const REASON_MISSING_IDENTIFICATION = 'missing_identification';

    public const REASON_DNI_MISMATCH = 'dni_mismatch';

    public const REASON_NAME_MISMATCH = 'name_mismatch';

    public const REASON_BOTH_MISMATCH = 'both_mismatch';

    public const REASON_CALLBACK_EXCEPTION = 'callback_exception';

    public const REASON_DOCS_ILLEGIBLE = 'docs_illegible';

    public const REASON_SELFIE_MISMATCH = 'selfie_mismatch';

    public const REASON_DOCUMENT_MISMATCH = 'document_mismatch';

    public const REASON_EXPIRED_OR_INVALID_DOCUMENT = 'expired_or_invalid_document';

    public const REASON_SUSPECTED_FRAUD = 'suspected_fraud';

    public const REASON_OTHER = 'other';

    public const REASON_APPROVED_FROM_MP_REJECTION = 'approved_from_mp_rejection';

    /**
     * @var list<string>
     */
    public const MANUAL_REJECT_REASONS = [
        self::REASON_DOCS_ILLEGIBLE,
        self::REASON_SELFIE_MISMATCH,
        self::REASON_DOCUMENT_MISMATCH,
        self::REASON_EXPIRED_OR_INVALID_DOCUMENT,
        self::REASON_SUSPECTED_FRAUD,
        self::REASON_OTHER,
    ];

    /**
     * @var list<string>
     */
    public const CLIENT_EVENT_NAMES = [
        self::NAME_CONFIRM_MODAL_SHOWN,
        self::NAME_CONFIRM_MODAL_CANCELLED,
    ];

    /**
     * @var list<string>
     */
    private const PII_METADATA_KEYS = [
        'user_name',
        'mp_name',
        'user_dni',
        'mp_dni',
        'nro_doc',
        'name',
        'first_name',
        'last_name',
        'identification',
        'mp_payload',
        'email',
        'phone',
    ];

    /**
     * @var list<string>
     */
    private const ERROR_REASONS = [
        self::REASON_TOKEN_EXCHANGE_FAILED,
        self::REASON_USERS_ME_FAILED,
        self::REASON_CALLBACK_EXCEPTION,
    ];

    public function __construct(private IdentityVerificationEventRecorder $recorder) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function emit(array $payload): void
    {
        $normalized = $this->normalize($payload);
        $this->log($normalized);
        $this->recorder->record($this->attributesForStorage($normalized));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $metadata = $this->stripPii($metadata);

        return [
            'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            'method' => (string) ($payload['method'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'reason' => isset($payload['reason']) && $payload['reason'] !== '' ? (string) $payload['reason'] : null,
            'attempt_id' => $payload['attempt_id'] ?? null,
            'related_type' => $payload['related_type'] ?? null,
            'related_id' => $payload['related_id'] ?? null,
            'surface' => $payload['surface'] ?? null,
            'platform' => $payload['platform'] ?? null,
            'app_version' => $payload['app_version'] ?? null,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function log(array $normalized): void
    {
        $reason = $normalized['reason'] ?? '';
        $message = sprintf(
            'Identity verification %s %s reason=%s',
            $normalized['method'],
            $normalized['name'],
            $reason ?? ''
        );

        $context = array_filter([
            'user_id' => $normalized['user_id'],
            'attempt_id' => $normalized['attempt_id'],
            'reason' => $normalized['reason'],
            'related_id' => $normalized['related_id'],
            'surface' => $normalized['surface'],
            'platform' => $normalized['platform'],
            'app_version' => $normalized['app_version'],
        ], fn ($value) => $value !== null && $value !== '');

        foreach ($normalized['metadata'] as $key => $value) {
            $context[$key] = $value;
        }

        $level = $this->logLevel($normalized['name'], $normalized['reason']);
        Log::{$level}($message, $context);
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function attributesForStorage(array $normalized): array
    {
        $attributes = $normalized;
        if ($attributes['metadata'] === []) {
            $attributes['metadata'] = null;
        }
        if ($attributes['user_id'] === 0) {
            $attributes['user_id'] = null;
        }

        return $attributes;
    }

    private function logLevel(string $name, ?string $reason): string
    {
        if (in_array($reason, self::ERROR_REASONS, true)) {
            return 'error';
        }

        if (in_array($name, [self::NAME_FAILED, self::NAME_PAYMENT_FAILED, self::NAME_UPLOAD_REJECTED], true)) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function stripPii(array $metadata): array
    {
        foreach (self::PII_METADATA_KEYS as $key) {
            unset($metadata[$key]);
        }

        return $metadata;
    }
}
