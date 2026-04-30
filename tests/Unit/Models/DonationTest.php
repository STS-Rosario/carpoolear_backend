<?php

namespace Tests\Unit\Models;

use STS\Models\Donation;
use STS\Models\User;
use Tests\TestCase;

class DonationTest extends TestCase
{
    public function test_fillable_contains_expected_mass_assignable_attributes(): void
    {
        $this->assertSame([
            'user_id',
            'month',
            'has_donated',
            'has_denied',
            'ammount',
        ], (new Donation)->getFillable());
    }

    public function test_boolean_casts_for_has_donated_and_has_denied(): void
    {
        $user = User::factory()->create();
        $donation = Donation::query()->create([
            'user_id' => $user->id,
            'month' => now(),
            'has_donated' => 1,
            'has_denied' => 0,
            'ammount' => 50,
        ]);

        $donation = $donation->fresh();
        $this->assertTrue($donation->has_donated);
        $this->assertFalse($donation->has_denied);
    }

    public function test_persists_user_month_and_ammount(): void
    {
        $user = User::factory()->create();
        $month = '2026-02-15 10:00:00';

        $donation = Donation::query()->create([
            'user_id' => $user->id,
            'month' => $month,
            'has_donated' => false,
            'has_denied' => false,
            'ammount' => 1_234,
        ]);

        $donation = $donation->fresh();
        $this->assertSame($user->id, $donation->user_id);
        $this->assertSame(1_234, (int) $donation->ammount);
        $this->assertStringContainsString('2026-02-15', (string) $donation->month);
    }

    public function test_mass_assignment_persists_all_fillable_fields(): void
    {
        $user = User::factory()->create();

        $donation = Donation::query()->create([
            'user_id' => $user->id,
            'month' => '2026-03-01 00:00:00',
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 987,
        ])->fresh();

        $this->assertSame($user->id, (int) $donation->user_id);
        $this->assertTrue((bool) $donation->has_donated);
        $this->assertFalse((bool) $donation->has_denied);
        $this->assertSame(987, (int) $donation->ammount);
        $this->assertStringContainsString('2026-03-01', (string) $donation->month);
    }

    public function test_query_scoped_to_user_id(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Donation::query()->create([
            'user_id' => $u1->id,
            'month' => now(),
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 1,
        ]);
        Donation::query()->create([
            'user_id' => $u2->id,
            'month' => now(),
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 2,
        ]);

        $this->assertSame(1, Donation::query()->where('user_id', $u1->id)->count());
        $this->assertSame(1, Donation::query()->where('user_id', $u2->id)->count());
    }
}
