<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\DeleteAccountRequest;
use STS\Models\User;
use Tests\TestCase;

class DeleteAccountRequestTest extends TestCase
{
    public function test_fillable_contains_expected_mass_assignable_attributes(): void
    {
        $this->assertSame([
            'user_id',
            'date_requested',
            'action_taken',
            'action_taken_date',
        ], (new DeleteAccountRequest)->getFillable());
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $row = DeleteAccountRequest::query()->create([
            'user_id' => $user->id,
            'date_requested' => '2026-06-01 09:00:00',
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
            'action_taken_date' => null,
        ]);

        $this->assertTrue($row->fresh()->user->is($user));
    }

    public function test_date_requested_and_action_taken_date_cast_to_carbon(): void
    {
        $user = User::factory()->create();

        $row = DeleteAccountRequest::query()->create([
            'user_id' => $user->id,
            'date_requested' => '2026-07-10 12:30:00',
            'action_taken' => DeleteAccountRequest::ACTION_DELETED,
            'action_taken_date' => '2026-07-11 08:15:00',
        ]);

        $row = $row->fresh();
        $this->assertInstanceOf(Carbon::class, $row->date_requested);
        $this->assertSame('2026-07-10 12:30:00', $row->date_requested->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(Carbon::class, $row->action_taken_date);
        $this->assertSame('2026-07-11 08:15:00', $row->action_taken_date->format('Y-m-d H:i:s'));
    }

    public function test_action_taken_constants_and_persistence(): void
    {
        $user = User::factory()->create();

        $requested = DeleteAccountRequest::query()->create([
            'user_id' => $user->id,
            'date_requested' => now(),
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
            'action_taken_date' => null,
        ]);
        $deleted = DeleteAccountRequest::query()->create([
            'user_id' => $user->id,
            'date_requested' => now(),
            'action_taken' => DeleteAccountRequest::ACTION_DELETED,
            'action_taken_date' => now(),
        ]);
        $rejected = DeleteAccountRequest::query()->create([
            'user_id' => $user->id,
            'date_requested' => now(),
            'action_taken' => DeleteAccountRequest::ACTION_REJECTED,
            'action_taken_date' => now(),
        ]);

        $this->assertSame(0, DeleteAccountRequest::ACTION_REQUESTED);
        $this->assertSame(1, DeleteAccountRequest::ACTION_DELETED);
        $this->assertSame(2, DeleteAccountRequest::ACTION_REJECTED);

        $this->assertSame(DeleteAccountRequest::ACTION_REQUESTED, (int) $requested->fresh()->action_taken);
        $this->assertSame(DeleteAccountRequest::ACTION_DELETED, (int) $deleted->fresh()->action_taken);
        $this->assertSame(DeleteAccountRequest::ACTION_REJECTED, (int) $rejected->fresh()->action_taken);
    }

    public function test_allows_null_action_taken_date_while_pending(): void
    {
        $user = User::factory()->create();

        $row = DeleteAccountRequest::query()->create([
            'user_id' => $user->id,
            'date_requested' => now(),
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
            'action_taken_date' => null,
        ]);

        $this->assertNull($row->fresh()->action_taken_date);
    }

    public function test_table_name_is_delete_account_requests(): void
    {
        $this->assertSame('delete_account_requests', (new DeleteAccountRequest)->getTable());
    }
}
