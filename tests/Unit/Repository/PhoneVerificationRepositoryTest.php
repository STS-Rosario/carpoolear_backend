<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Mockery;
use STS\Models\PhoneVerification;
use STS\Models\User;
use STS\Repository\PhoneVerificationRepository;
use Tests\TestCase;

class PhoneVerificationRepositoryTest extends TestCase
{
    private function repo(): PhoneVerificationRepository
    {
        return new PhoneVerificationRepository;
    }

    public function test_create_update_find_and_delete(): void
    {
        $user = User::factory()->create();
        $row = new PhoneVerification([
            'user_id' => $user->id,
            'phone_number' => '+54911'.random_int(10000000, 99999999),
            'verified' => false,
            'verification_code' => '123456',
        ]);

        $this->assertTrue($this->repo()->create($row));
        $this->assertNotNull($row->id);

        $row->verification_code = '654321';
        $this->assertTrue($this->repo()->update($row));

        $found = $this->repo()->find($row->id);
        $this->assertNotNull($found);
        $this->assertSame('654321', $found->verification_code);

        $this->assertTrue((bool) $this->repo()->delete($found));
        $this->assertNull(PhoneVerification::query()->find($row->id));
    }

    public function test_find_returns_null_when_phone_verification_missing(): void
    {
        // Mutation intent: preserve `PhoneVerification::find($id)` absent-row behavior (~28–31).
        $missingId = (PhoneVerification::query()->max('id') ?? 0) + 999999;

        $this->assertNull($this->repo()->find($missingId));
    }

    public function test_create_returns_false_when_save_fails(): void
    {
        $row = Mockery::mock(PhoneVerification::class);
        $row->shouldReceive('save')->once()->andReturn(false);

        $this->assertFalse($this->repo()->create($row));
    }

    public function test_update_returns_false_when_save_fails(): void
    {
        $row = Mockery::mock(PhoneVerification::class);
        $row->shouldReceive('save')->once()->andReturn(false);

        $this->assertFalse($this->repo()->update($row));
    }

    public function test_delete_returns_false_when_delete_fails(): void
    {
        $row = Mockery::mock(PhoneVerification::class);
        $row->shouldReceive('delete')->once()->andReturn(false);

        $this->assertFalse($this->repo()->delete($row));
    }

    public function test_create_invokes_save(): void
    {
        // Mutation intent: preserve `return $phoneVerification->save()` (~12–15 RemoveMethodCall).
        $row = Mockery::mock(PhoneVerification::class);
        $row->shouldReceive('save')->once()->andReturn(true);

        $this->assertTrue($this->repo()->create($row));
    }

    public function test_update_invokes_save(): void
    {
        // Mutation intent: preserve `return $phoneVerification->save()` (~18–22 RemoveMethodCall).
        $row = Mockery::mock(PhoneVerification::class);
        $row->shouldReceive('save')->once()->andReturn(true);

        $this->assertTrue($this->repo()->update($row));
    }

    public function test_delete_invokes_delete(): void
    {
        // Mutation intent: preserve `return $phoneVerification->delete()` (~78–81 RemoveMethodCall).
        $row = Mockery::mock(PhoneVerification::class);
        $row->shouldReceive('delete')->once()->andReturn(true);

        $this->assertTrue($this->repo()->delete($row));
    }

    public function test_get_latest_unverified_by_user_returns_null_when_no_unverified_rows(): void
    {
        // Mutation intent: preserve `where('verified', false)` + empty first() (~36–41).
        $user = User::factory()->create();

        $this->assertNull($this->repo()->getLatestUnverifiedByUser($user->id));

        $phone = '+54911'.random_int(10000000, 99999999);
        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $this->assertNull($this->repo()->getLatestUnverifiedByUser($user->id));
    }

    public function test_get_latest_by_user_returns_null_when_user_has_none(): void
    {
        // Mutation intent: preserve `where('user_id', …)->latest()->first()` miss (~47–51).
        $user = User::factory()->create();

        $this->assertNull($this->repo()->getLatestByUser($user->id));
    }

    public function test_get_by_user_returns_empty_when_none(): void
    {
        // Mutation intent: preserve empty `get()` (~68–72).
        $user = User::factory()->create();

        $rows = $this->repo()->getByUser($user->id);

        $this->assertCount(0, $rows);
    }

    public function test_get_verification_stats_returns_zero_totals_when_user_has_no_attempts(): void
    {
        // Mutation intent: preserve COUNT/SUM aggregate shape when WHERE matches zero rows (~88–94).
        $user = User::factory()->create();

        $stats = $this->repo()->getVerificationStats($user->id);

        $this->assertNotNull($stats);
        $this->assertSame(0, (int) $stats->total_attempts);
        $this->assertSame(0, (int) ($stats->successful_verifications ?? 0));
        $this->assertSame(0, (int) ($stats->failed_attempts ?? 0));
    }

    public function test_get_latest_unverified_by_user_returns_newest_pending_only(): void
    {
        $user = User::factory()->create();
        $phone = '+54911'.random_int(10000000, 99999999);

        $older = PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => false,
        ]);
        $older->forceFill(['created_at' => Carbon::now()->subHours(2)])->saveQuietly();

        $newer = PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => false,
        ]);

        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => true,
        ]);

        $latest = $this->repo()->getLatestUnverifiedByUser($user->id);

        $this->assertNotNull($latest);
        $this->assertTrue($latest->is($newer));
    }

    public function test_get_latest_by_user_ignores_verified_flag_for_ordering(): void
    {
        $user = User::factory()->create();
        $phone = '+54911'.random_int(10000000, 99999999);

        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => false,
        ])->forceFill(['created_at' => Carbon::now()->subDay()])->saveQuietly();

        $verified = PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $latest = $this->repo()->getLatestByUser($user->id);

        $this->assertTrue($latest->is($verified));
    }

    public function test_is_phone_verified_by_another_user_excludes_given_user(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $phone = '+54911'.random_int(10000000, 99999999);

        PhoneVerification::create([
            'user_id' => $u1->id,
            'phone_number' => $phone,
            'verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        $this->assertNull($this->repo()->isPhoneVerifiedByAnotherUser($phone, $u1->id));

        $conflict = $this->repo()->isPhoneVerifiedByAnotherUser($phone, $u2->id);
        $this->assertNotNull($conflict);
        $this->assertSame($u1->id, $conflict->user_id);
    }

    public function test_is_phone_verified_by_another_user_returns_null_when_phone_unknown(): void
    {
        // Mutation intent: preserve `where('phone_number', …)` miss (~57–62).
        $viewer = User::factory()->create();
        $unknown = '+54999'.random_int(10000000, 99999999);

        $this->assertNull($this->repo()->isPhoneVerifiedByAnotherUser($unknown, $viewer->id));
    }

    public function test_is_phone_verified_by_another_user_returns_null_when_only_unverified_rows_exist(): void
    {
        // Mutation intent: preserve `where('verified', true)` (~59–61).
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $phone = '+54911'.random_int(10000000, 99999999);

        PhoneVerification::create([
            'user_id' => $u1->id,
            'phone_number' => $phone,
            'verified' => false,
        ]);

        $this->assertNull($this->repo()->isPhoneVerifiedByAnotherUser($phone, $u2->id));
    }

    public function test_get_by_user_orders_newest_first(): void
    {
        $user = User::factory()->create();
        $phone = '+54911'.random_int(10000000, 99999999);

        $first = PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => false,
        ]);
        $first->forceFill(['created_at' => Carbon::now()->subHours(3)])->saveQuietly();

        $second = PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => false,
        ]);
        $second->forceFill(['created_at' => Carbon::now()->subHour()])->saveQuietly();

        $rows = $this->repo()->getByUser($user->id);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->first()->is($second));
        $this->assertTrue($rows->last()->is($first));
    }

    public function test_get_verification_stats_aggregates_counts(): void
    {
        $user = User::factory()->create();
        $phone = '+54911'.random_int(10000000, 99999999);

        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => false,
        ]);
        PhoneVerification::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'verified' => false,
        ]);

        $stats = $this->repo()->getVerificationStats($user->id);

        $this->assertSame(3, (int) $stats->total_attempts);
        $this->assertSame(1, (int) $stats->successful_verifications);
        $this->assertSame(2, (int) $stats->failed_attempts);
    }
}
