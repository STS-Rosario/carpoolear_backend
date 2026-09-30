<?php

namespace STS\Services;

use InvalidArgumentException;

/**
 * Single source of truth for turning identity_verification_events into one outcome class per attempt.
 *
 * Every rule is declared once in the constant maps below. The PHP methods and the SQL fragment
 * builders are both derived from those maps, so reports aggregated in SQL cannot drift from the
 * documented (and unit-tested) PHP semantics.
 */
class IdentityVerificationAttemptClassifier
{
    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const ERROR = 'error';

    public const CANCELLED = 'cancelled';

    public const ABANDONED = 'abandoned';

    public const INCONCLUSIVE = 'inconclusive';

    public const PENDING_REVIEW = 'pending_review';

    /** @var list<string> */
    public const AUTOMATIC_CLASSES = [self::APPROVED, self::REJECTED, self::ERROR, self::CANCELLED, self::ABANDONED];

    /** @var list<string> */
    public const MANUAL_CLASSES = [self::APPROVED, self::REJECTED, self::INCONCLUSIVE, self::PENDING_REVIEW];

    public const RESOLVED_BY_MERCADO_PAGO = 'mercado_pago';

    public const RESOLVED_BY_MANUAL = 'manual';

    public const RESOLVED_BY_MP_REJECTION_APPROVED = 'mp_rejection_approved';

    public const RESOLVED_BY_ADMIN_EDIT = 'admin_edit';

    /** @var list<string> */
    public const RESOLUTION_METHODS = [
        self::RESOLVED_BY_MERCADO_PAGO,
        self::RESOLVED_BY_MANUAL,
        self::RESOLVED_BY_MP_REJECTION_APPROVED,
        self::RESOLVED_BY_ADMIN_EDIT,
    ];

    public const MANUAL_RELATED_TYPE = 'manual_identity_validations';

    /**
     * MP `failed` reasons by class. Any other (or unknown / null) failure reason is an error.
     *
     * @var array<string, list<string>>
     */
    private const MP_FAILURE_REASON_CLASSES = [
        self::REJECTED => [
            IdentityVerificationOutcome::REASON_DNI_MISMATCH,
            IdentityVerificationOutcome::REASON_NAME_MISMATCH,
            IdentityVerificationOutcome::REASON_BOTH_MISMATCH,
            IdentityVerificationOutcome::REASON_MISSING_IDENTIFICATION,
        ],
        self::CANCELLED => [
            IdentityVerificationOutcome::REASON_OAUTH_CANCELLED,
            IdentityVerificationOutcome::REASON_OAUTH_DENIED,
        ],
    ];

    /**
     * Manual request state after each state-changing event (latest one wins).
     * A string maps the event name; an array maps the event reason (admin overrides carry the new status as reason).
     *
     * @var array<string, string|array<string, string>>
     */
    private const MANUAL_STATE_EVENTS = [
        IdentityVerificationOutcome::NAME_PAYMENT_SUCCEEDED => self::INCONCLUSIVE,
        IdentityVerificationOutcome::NAME_DOCS_SUBMITTED => self::PENDING_REVIEW,
        IdentityVerificationOutcome::NAME_INFO_REQUESTED => self::INCONCLUSIVE,
        IdentityVerificationOutcome::NAME_SUCCEEDED => self::APPROVED,
        IdentityVerificationOutcome::NAME_FAILED => self::REJECTED,
        IdentityVerificationOutcome::NAME_CLOSED_AFTER_MP_SUCCESS => self::INCONCLUSIVE,
        IdentityVerificationOutcome::NAME_ADMIN_STATE_CHANGED => [
            'approved' => self::APPROVED,
            'rejected' => self::REJECTED,
            'pending' => self::PENDING_REVIEW,
            'awaiting_photos' => self::INCONCLUSIVE,
            'closed' => self::INCONCLUSIVE,
        ],
    ];

    /**
     * Events that prove a manual request was paid (the unit of a manual attempt).
     * closed_after_mp_success and an admin "closed" override can hit unpaid requests, so they do not count.
     *
     * @var array<string, true|list<string>>
     */
    private const MANUAL_ATTEMPT_EVIDENCE = [
        IdentityVerificationOutcome::NAME_PAYMENT_SUCCEEDED => true,
        IdentityVerificationOutcome::NAME_DOCS_SUBMITTED => true,
        IdentityVerificationOutcome::NAME_SUCCEEDED => true,
        IdentityVerificationOutcome::NAME_FAILED => true,
        IdentityVerificationOutcome::NAME_INFO_REQUESTED => true,
        IdentityVerificationOutcome::NAME_ADMIN_STATE_CHANGED => ['approved', 'rejected', 'pending', 'awaiting_photos'],
    ];

    public function classifyMercadoPago(?string $outcomeName, ?string $reason): string
    {
        if ($outcomeName === IdentityVerificationOutcome::NAME_SUCCEEDED) {
            return self::APPROVED;
        }
        if ($outcomeName !== IdentityVerificationOutcome::NAME_FAILED) {
            return self::ABANDONED;
        }
        foreach (self::MP_FAILURE_REASON_CLASSES as $class => $reasons) {
            if (in_array($reason, $reasons, true)) {
                return $class;
            }
        }

        return self::ERROR;
    }

    public function classifyManualState(string $name, ?string $reason): ?string
    {
        $mapped = self::MANUAL_STATE_EVENTS[$name] ?? null;
        if (is_array($mapped)) {
            return $mapped[(string) $reason] ?? null;
        }

        return $mapped;
    }

    public function isManualAttemptEvidence(string $name, ?string $reason): bool
    {
        $rule = self::MANUAL_ATTEMPT_EVIDENCE[$name] ?? false;

        return is_array($rule) ? in_array($reason, $rule, true) : $rule;
    }

    public function approvalMethod(string $method, string $name, ?string $reason, ?string $relatedType): ?string
    {
        if ($method === IdentityVerificationOutcome::METHOD_MERCADO_PAGO && $name === IdentityVerificationOutcome::NAME_SUCCEEDED) {
            return self::RESOLVED_BY_MERCADO_PAGO;
        }
        if ($method === IdentityVerificationOutcome::METHOD_MANUAL
            && $name === IdentityVerificationOutcome::NAME_SUCCEEDED
            && $reason === IdentityVerificationOutcome::REASON_APPROVED_FROM_MP_REJECTION) {
            return self::RESOLVED_BY_MP_REJECTION_APPROVED;
        }
        if ($method === IdentityVerificationOutcome::METHOD_MANUAL
            && $relatedType === self::MANUAL_RELATED_TYPE
            && $this->classifyManualState($name, $reason) === self::APPROVED) {
            return self::RESOLVED_BY_MANUAL;
        }
        if ($method === IdentityVerificationOutcome::METHOD_ADMIN
            && $name === IdentityVerificationOutcome::NAME_ADMIN_IDENTITY_EDITED
            && $reason === IdentityVerificationOutcome::REASON_VALIDATED) {
            return self::RESOLVED_BY_ADMIN_EDIT;
        }

        return null;
    }

    /**
     * CASE expression yielding the automatic class for an MP outcome (name/reason columns of the outcome event; NULL name = no outcome).
     */
    public function mercadoPagoOutcomeSql(string $nameColumn, string $reasonColumn): string
    {
        $sql = 'CASE WHEN '.$nameColumn.' = '.$this->quote(IdentityVerificationOutcome::NAME_SUCCEEDED).' THEN '.$this->quote(self::APPROVED)
            .' WHEN '.$nameColumn.' IS NULL OR '.$nameColumn.' <> '.$this->quote(IdentityVerificationOutcome::NAME_FAILED).' THEN '.$this->quote(self::ABANDONED);
        foreach (self::MP_FAILURE_REASON_CLASSES as $class => $reasons) {
            $sql .= ' WHEN '.$reasonColumn.' IN ('.$this->quoteList($reasons).') THEN '.$this->quote($class);
        }

        return $sql.' ELSE '.$this->quote(self::ERROR).' END';
    }

    /**
     * CASE expression yielding the manual class a state event puts the request in, or NULL when the event is not a state change.
     */
    public function manualStateSql(string $nameColumn, string $reasonColumn): string
    {
        $sql = 'CASE';
        foreach (self::MANUAL_STATE_EVENTS as $name => $mapped) {
            if (is_array($mapped)) {
                foreach ($mapped as $reason => $class) {
                    $sql .= ' WHEN '.$nameColumn.' = '.$this->quote($name).' AND '.$reasonColumn.' = '.$this->quote($reason).' THEN '.$this->quote($class);
                }

                continue;
            }
            $sql .= ' WHEN '.$nameColumn.' = '.$this->quote($name).' THEN '.$this->quote($mapped);
        }

        return $sql.' ELSE NULL END';
    }

    /**
     * Boolean SQL condition: the event proves its manual request was paid.
     */
    public function manualAttemptEvidenceSql(string $nameColumn, string $reasonColumn): string
    {
        $plain = [];
        $parts = [];
        foreach (self::MANUAL_ATTEMPT_EVIDENCE as $name => $rule) {
            if (is_array($rule)) {
                $parts[] = '('.$nameColumn.' = '.$this->quote($name).' AND '.$reasonColumn.' IN ('.$this->quoteList($rule).'))';

                continue;
            }
            $plain[] = $name;
        }
        array_unshift($parts, $nameColumn.' IN ('.$this->quoteList($plain).')');

        return '('.implode(' OR ', $parts).')';
    }

    /**
     * CASE expression yielding the resolution method of an approval event, or NULL when the event is not an approval.
     */
    public function approvalMethodSql(string $methodColumn, string $nameColumn, string $reasonColumn, string $relatedTypeColumn): string
    {
        $succeeded = $this->quote(IdentityVerificationOutcome::NAME_SUCCEEDED);
        $manual = $this->quote(IdentityVerificationOutcome::METHOD_MANUAL);

        return 'CASE'
            .' WHEN '.$methodColumn.' = '.$this->quote(IdentityVerificationOutcome::METHOD_MERCADO_PAGO).' AND '.$nameColumn.' = '.$succeeded
            .' THEN '.$this->quote(self::RESOLVED_BY_MERCADO_PAGO)
            .' WHEN '.$methodColumn.' = '.$manual.' AND '.$nameColumn.' = '.$succeeded
            .' AND '.$reasonColumn.' = '.$this->quote(IdentityVerificationOutcome::REASON_APPROVED_FROM_MP_REJECTION)
            .' THEN '.$this->quote(self::RESOLVED_BY_MP_REJECTION_APPROVED)
            .' WHEN '.$methodColumn.' = '.$manual.' AND '.$relatedTypeColumn.' = '.$this->quote(self::MANUAL_RELATED_TYPE)
            .' AND '.$this->manualStateSql($nameColumn, $reasonColumn).' = '.$this->quote(self::APPROVED)
            .' THEN '.$this->quote(self::RESOLVED_BY_MANUAL)
            .' WHEN '.$methodColumn.' = '.$this->quote(IdentityVerificationOutcome::METHOD_ADMIN)
            .' AND '.$nameColumn.' = '.$this->quote(IdentityVerificationOutcome::NAME_ADMIN_IDENTITY_EDITED)
            .' AND '.$reasonColumn.' = '.$this->quote(IdentityVerificationOutcome::REASON_VALIDATED)
            .' THEN '.$this->quote(self::RESOLVED_BY_ADMIN_EDIT)
            .' ELSE NULL END';
    }

    /**
     * @param  list<string>  $values
     */
    private function quoteList(array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => $this->quote($value), $values));
    }

    /**
     * Values are internal constants; refuse anything that is not a plain identifier so inlining stays injection-safe.
     */
    private function quote(string $value): string
    {
        if (! preg_match('/^[a-z_]+$/', $value)) {
            throw new InvalidArgumentException('Unsafe SQL literal: '.$value);
        }

        return "'".$value."'";
    }
}
