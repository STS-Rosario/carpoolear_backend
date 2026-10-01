<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use STS\Services\IdentityVerificationAttemptClassifier as C;
use Tests\TestCase;

class IdentityVerificationAttemptClassifierTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    public static function mercadoPagoCases(): array
    {
        return [
            'no outcome' => [null, null, 'abandoned'],
            'succeeded' => ['succeeded', null, 'approved'],
            'dni mismatch' => ['failed', 'dni_mismatch', 'rejected'],
            'name mismatch' => ['failed', 'name_mismatch', 'rejected'],
            'both mismatch' => ['failed', 'both_mismatch', 'rejected'],
            'missing identification' => ['failed', 'missing_identification', 'rejected'],
            'oauth cancelled' => ['failed', 'oauth_cancelled', 'cancelled'],
            'oauth denied' => ['failed', 'oauth_denied', 'cancelled'],
            'token exchange' => ['failed', 'token_exchange_failed', 'error'],
            'users me' => ['failed', 'users_me_failed', 'error'],
            'missing access token' => ['failed', 'missing_access_token', 'error'],
            'callback exception' => ['failed', 'callback_exception', 'error'],
            'invalid state' => ['failed', 'invalid_or_expired_state', 'error'],
            'missing code' => ['failed', 'missing_code_or_state', 'error'],
            'user not found' => ['failed', 'user_not_found', 'error'],
            'unknown reason' => ['failed', 'something_new', 'error'],
            'null reason' => ['failed', null, 'error'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: ?string}>
     */
    public static function manualCases(): array
    {
        return [
            'paid, no docs' => ['payment_succeeded', null, 'inconclusive'],
            'docs submitted' => ['docs_submitted', null, 'pending_review'],
            'info requested' => ['info_requested', null, 'inconclusive'],
            'approved' => ['succeeded', null, 'approved'],
            'rejected' => ['failed', 'docs_illegible', 'rejected'],
            'closed after mp success' => ['closed_after_mp_success', null, 'inconclusive'],
            'override approved' => ['admin_state_changed', 'approved', 'approved'],
            'override rejected' => ['admin_state_changed', 'rejected', 'rejected'],
            'override pending' => ['admin_state_changed', 'pending', 'pending_review'],
            'override awaiting photos' => ['admin_state_changed', 'awaiting_photos', 'inconclusive'],
            'override closed' => ['admin_state_changed', 'closed', 'inconclusive'],
            'payment started is not a state' => ['payment_started', null, null],
            'upload rejected is not a state' => ['upload_rejected', null, null],
            'payment failed is not a state' => ['payment_failed', null, null],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: ?string, 4: ?string}>
     */
    public static function approvalCases(): array
    {
        return [
            'mp success' => ['mercado_pago', 'succeeded', null, null, 'mercado_pago'],
            'manual review approve' => ['manual', 'succeeded', null, 'manual_identity_validations', 'manual'],
            'manual override approve' => ['manual', 'admin_state_changed', 'approved', 'manual_identity_validations', 'manual'],
            'manual override closed' => ['manual', 'admin_state_changed', 'closed', 'manual_identity_validations', null],
            'mp rejection approved' => ['manual', 'succeeded', 'approved_from_mp_rejection', 'mercado_pago_rejected_validations', 'mp_rejection_approved'],
            'mp rejection rejected' => ['manual', 'failed', 'rejected_from_mp_rejection', 'mercado_pago_rejected_validations', null],
            'admin edit validated' => ['admin', 'admin_identity_edited', 'validated', 'users', 'admin_edit'],
            'admin edit unvalidated' => ['admin', 'admin_identity_edited', 'unvalidated', 'users', null],
            'mp failure' => ['mercado_pago', 'failed', 'dni_mismatch', null, null],
            'manual docs' => ['manual', 'docs_submitted', null, 'manual_identity_validations', null],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: bool}>
     */
    public static function manualEvidenceCases(): array
    {
        return [
            'payment succeeded' => ['payment_succeeded', null, true],
            'docs submitted' => ['docs_submitted', null, true],
            'succeeded' => ['succeeded', null, true],
            'failed' => ['failed', 'other', true],
            'info requested' => ['info_requested', null, true],
            'override approved' => ['admin_state_changed', 'approved', true],
            'override awaiting photos' => ['admin_state_changed', 'awaiting_photos', true],
            'override closed (may be unpaid)' => ['admin_state_changed', 'closed', false],
            'closed after mp success (may be unpaid)' => ['closed_after_mp_success', null, false],
            'payment started (unpaid)' => ['payment_started', null, false],
            'payment failed' => ['payment_failed', null, false],
            'upload rejected' => ['upload_rejected', null, false],
        ];
    }

    #[DataProvider('mercadoPagoCases')]
    public function test_mercado_pago_classification(?string $name, ?string $reason, string $expected): void
    {
        $this->assertSame($expected, (new C)->classifyMercadoPago($name, $reason));
        $this->assertSame($expected, $this->sqlValue((new C)->mercadoPagoOutcomeSql('n', 'r'), ['n' => $name, 'r' => $reason]));
    }

    #[DataProvider('manualCases')]
    public function test_manual_classification(string $name, ?string $reason, ?string $expected): void
    {
        $this->assertSame($expected, (new C)->classifyManualState($name, $reason));
        $this->assertSame($expected, $this->sqlValue((new C)->manualStateSql('n', 'r'), ['n' => $name, 'r' => $reason]));
    }

    #[DataProvider('approvalCases')]
    public function test_approval_method(string $method, string $name, ?string $reason, ?string $relatedType, ?string $expected): void
    {
        $this->assertSame($expected, (new C)->approvalMethod($method, $name, $reason, $relatedType));
        $this->assertSame($expected, $this->sqlValue(
            (new C)->approvalMethodSql('m', 'n', 'r', 't'),
            ['m' => $method, 'n' => $name, 'r' => $reason, 't' => $relatedType]
        ));
    }

    #[DataProvider('manualEvidenceCases')]
    public function test_manual_attempt_evidence(string $name, ?string $reason, bool $expected): void
    {
        $this->assertSame($expected, (new C)->isManualAttemptEvidence($name, $reason));
        $sql = 'CASE WHEN '.(new C)->manualAttemptEvidenceSql('n', 'r').' THEN 1 ELSE 0 END';
        $this->assertSame($expected ? '1' : '0', (string) $this->sqlValue($sql, ['n' => $name, 'r' => $reason]));
    }

    public function test_class_lists_are_complete_and_ordered(): void
    {
        $this->assertSame(['approved', 'rejected', 'error', 'cancelled', 'abandoned'], C::AUTOMATIC_CLASSES);
        $this->assertSame(['approved', 'rejected', 'inconclusive', 'pending_review'], C::MANUAL_CLASSES);
        $this->assertSame(['mercado_pago', 'manual', 'mp_rejection_approved', 'admin_edit'], C::RESOLUTION_METHODS);
    }

    /**
     * Evaluate a SQL expression against a one-row derived table so the SQL mapping is proven equal to the PHP one.
     *
     * @param  array<string, ?string>  $columns
     */
    private function sqlValue(string $expression, array $columns): mixed
    {
        $select = [];
        foreach (array_keys($columns) as $column) {
            $select[] = 'CAST(? AS CHAR(64)) AS '.$column;
        }
        $row = DB::selectOne(
            'SELECT '.$expression.' AS v FROM (SELECT '.implode(', ', $select).') t',
            array_values($columns)
        );

        return $row->v;
    }
}
