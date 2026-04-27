<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
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
