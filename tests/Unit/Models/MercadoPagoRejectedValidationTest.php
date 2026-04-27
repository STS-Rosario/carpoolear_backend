<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use Tests\TestCase;

class MercadoPagoRejectedValidationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRow(User $user, array $overrides = []): MercadoPagoRejectedValidation
    {
        return MercadoPagoRejectedValidation::query()->create(array_merge([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['identification' => ['number' => '30123456']],
            'approved_at' => null,
            'approved_by' => null,
            'review_status' => null,
            'review_note' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ], $overrides));
    }

    public function test_belongs_to_user_approved_by_and_reviewed_by(): void
    {
        $subject = User::factory()->create();
        $approver = User::factory()->create();
        $reviewer = User::factory()->create();

        $row = $this->makeRow($subject, [
            'approved_at' => '2026-03-01 10:00:00',
            'approved_by' => $approver->id,
            'review_status' => 'approved',
            'review_note' => 'OK',
            'reviewed_at' => '2026-03-02 11:00:00',
            'reviewed_by' => $reviewer->id,
        ]);

        $row = $row->fresh();
        $this->assertTrue($row->user->is($subject));
        $this->assertTrue($row->approvedBy->is($approver));
        $this->assertTrue($row->reviewedBy->is($reviewer));
    }

    public function test_mp_payload_and_datetime_casts(): void
    {
        $user = User::factory()->create();
        $payload = ['user' => ['id' => 99], 'tags' => ['gold']];

        $row = $this->makeRow($user, [
            'mp_payload' => $payload,
            'approved_at' => '2026-04-10 15:30:00',
            'reviewed_at' => '2026-04-11 09:00:00',
        ]);

        $row = $row->fresh();
        $this->assertEquals($payload, $row->mp_payload);
        $this->assertIsArray($row->mp_payload);
        $this->assertInstanceOf(Carbon::class, $row->approved_at);
        $this->assertSame('2026-04-10 15:30:00', $row->approved_at->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(Carbon::class, $row->reviewed_at);
        $this->assertSame('2026-04-11 09:00:00', $row->reviewed_at->format('Y-m-d H:i:s'));
    }

    public function test_persists_reject_reason_and_review_fields(): void
    {
        $user = User::factory()->create();

        $row = $this->makeRow($user, [
            'reject_reason' => 'name_mismatch',
            'review_status' => 'rejected',
            'review_note' => 'Name does not match DNI.',
        ]);

        $row = $row->fresh();
        $this->assertSame('name_mismatch', $row->reject_reason);
        $this->assertSame('rejected', $row->review_status);
        $this->assertSame('Name does not match DNI.', $row->review_note);
    }

    public function test_table_name_is_mercado_pago_rejected_validations(): void
    {
        $this->assertSame('mercado_pago_rejected_validations', (new MercadoPagoRejectedValidation)->getTable());
    }
}
