<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\Car;
use STS\Models\Device;
use STS\Models\Donation;
use STS\Models\SocialAccount;
use STS\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_get_jwt_identifier_and_custom_claims(): void
    {
        $user = User::factory()->create();

        $this->assertSame($user->id, $user->getJWTIdentifier());
        $this->assertSame([], $user->getJWTCustomClaims());
    }

    public function test_friendship_constants(): void
    {
        $this->assertSame(0, User::FRIEND_REQUEST);
        $this->assertSame(1, User::FRIEND_ACCEPTED);
        $this->assertSame(2, User::FRIEND_REJECT);
        $this->assertSame(0, User::FRIENDSHIP_SYSTEM);
        $this->assertSame(1, User::FRIENDSHIP_FACEBOOK);
    }

    public function test_accounts_cars_and_devices_relationships(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'ext-'.uniqid('', true),
            'provider' => 'facebook',
        ]);
        Car::factory()->create(['user_id' => $user->id]);

        $device = new Device;
        $device->forceFill([
            'user_id' => $user->id,
            'device_id' => 'd-'.uniqid('', true),
            'device_type' => 'ios',
            'session_id' => 's-'.uniqid('', true),
            'app_version' => 1,
            'notifications' => true,
            'language' => 'es',
            'last_activity' => now()->toDateString(),
        ])->save();

        $user = $user->fresh();
        $this->assertSame(1, $user->accounts()->count());
        $this->assertSame(1, $user->cars()->count());
        $this->assertSame(1, $user->devices()->count());
    }

    public function test_donation_records_has_many_without_month_filter(): void
    {
        $user = User::factory()->create();

        Donation::query()->create([
            'user_id' => $user->id,
            'month' => '2025-01-15 00:00:00',
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 100,
        ]);
        Donation::query()->create([
            'user_id' => $user->id,
            'month' => '2026-03-01 00:00:00',
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 200,
        ]);

        $this->assertSame(2, $user->fresh()->donationRecords()->count());
    }

    public function test_age_returns_year_component_when_birthday_set(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $user = User::factory()->create(['birthday' => '2000-06-10']);

        $age = $user->fresh()->age();
        $this->assertIsInt($age);
        $this->assertGreaterThanOrEqual(25, $age);
        $this->assertLessThanOrEqual(26, $age);
    }

    public function test_driver_data_docs_casts_to_array(): void
    {
        $payload = ['license' => 'AB123', 'expires' => '2030-01-01'];
        $user = User::factory()->create(['driver_data_docs' => $payload]);

        $user = $user->fresh();
        $this->assertEquals($payload, $user->driver_data_docs);
        $this->assertIsArray($user->driver_data_docs);
    }

    public function test_to_array_hides_password_remember_token_and_private_note(): void
    {
        $user = User::factory()->create([
            'private_note' => 'admin only',
        ]);
        $array = $user->fresh()->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('terms_and_conditions', $array);
        $this->assertArrayNotHasKey('private_note', $array);
        $this->assertArrayHasKey('email', $array);
    }

    public function test_table_name_is_users(): void
    {
        $this->assertSame('users', (new User)->getTable());
    }
}
