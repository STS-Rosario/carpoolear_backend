<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\PaymentAttempt;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class PaymentAttemptTest extends TestCase
{
    public function test_belongs_to_trip_and_user(): void
    {
        $driver = User::factory()->create();
        $payer = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $attempt = PaymentAttempt::query()->create([
            'payment_id' => 'mp-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_PENDING,
            'trip_id' => $trip->id,
            'user_id' => $payer->id,
            'amount_cents' => 2_000,
            'error_message' => null,
            'payment_data' => null,
            'paid_at' => null,
        ]);

        $attempt = $attempt->fresh();
        $this->assertTrue($attempt->trip->is($trip));
        $this->assertTrue($attempt->user->is($payer));
    }

    public function test_payment_data_and_paid_at_casts(): void
    {
        $driver = User::factory()->create();
        $payer = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $attempt = PaymentAttempt::query()->create([
            'payment_id' => 'mp-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_COMPLETED,
            'trip_id' => $trip->id,
            'user_id' => $payer->id,
            'amount_cents' => 500,
            'error_message' => null,
            'payment_data' => ['fee' => 25, 'currency' => 'ARS'],
            'paid_at' => '2026-04-01 18:45:00',
        ]);

        $attempt = $attempt->fresh();
        $this->assertSame(['fee' => 25, 'currency' => 'ARS'], $attempt->payment_data);
        $this->assertInstanceOf(Carbon::class, $attempt->paid_at);
        $this->assertSame('2026-04-01 18:45:00', $attempt->paid_at->format('Y-m-d H:i:s'));
    }

    public function test_status_helper_methods(): void
    {
        $driver = User::factory()->create();
        $payer = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $pending = PaymentAttempt::query()->create([
            'payment_id' => 'mp-p-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_PENDING,
            'trip_id' => $trip->id,
            'user_id' => $payer->id,
            'amount_cents' => 100,
            'error_message' => null,
            'payment_data' => null,
            'paid_at' => null,
        ]);
        $completed = PaymentAttempt::query()->create([
            'payment_id' => 'mp-c-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_COMPLETED,
            'trip_id' => $trip->id,
            'user_id' => $payer->id,
            'amount_cents' => 100,
            'error_message' => null,
            'payment_data' => null,
            'paid_at' => now(),
        ]);
        $failed = PaymentAttempt::query()->create([
            'payment_id' => 'mp-f-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_FAILED,
            'trip_id' => $trip->id,
            'user_id' => $payer->id,
            'amount_cents' => 100,
            'error_message' => 'card_declined',
            'payment_data' => null,
            'paid_at' => null,
        ]);

        $this->assertTrue($pending->fresh()->isPending());
        $this->assertFalse($pending->fresh()->isCompleted());
        $this->assertFalse($pending->fresh()->isFailed());

        $this->assertTrue($completed->fresh()->isCompleted());
        $this->assertFalse($completed->fresh()->isPending());

        $this->assertTrue($failed->fresh()->isFailed());
        $this->assertFalse($failed->fresh()->isPending());
    }

    public function test_status_string_constants(): void
    {
        $this->assertSame('pending', PaymentAttempt::STATUS_PENDING);
        $this->assertSame('completed', PaymentAttempt::STATUS_COMPLETED);
        $this->assertSame('failed', PaymentAttempt::STATUS_FAILED);
    }

    public function test_persists_amount_cents_and_error_message(): void
    {
        $driver = User::factory()->create();
        $payer = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $attempt = PaymentAttempt::query()->create([
            'payment_id' => 'mp-'.uniqid('', true),
            'payment_status' => PaymentAttempt::STATUS_FAILED,
            'trip_id' => $trip->id,
            'user_id' => $payer->id,
            'amount_cents' => 12_345,
            'error_message' => 'insufficient_amount',
            'payment_data' => null,
            'paid_at' => null,
        ]);

        $attempt = $attempt->fresh();
        $this->assertSame(12_345, (int) $attempt->amount_cents);
        $this->assertSame('insufficient_amount', $attempt->error_message);
    }

    public function test_table_name_is_payment_attempts(): void
    {
        $this->assertSame('payment_attempts', (new PaymentAttempt)->getTable());
    }
}
