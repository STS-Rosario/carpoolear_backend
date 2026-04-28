<?php

namespace Tests\Unit\Services\Logic;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use STS\Events\User\Create as CreateEvent;
use STS\Events\User\Update as UpdateEvent;
use STS\Jobs\SendPasswordResetEmail;
use STS\Models\BannedUser;
use STS\Models\Car;
use STS\Models\Donation;
use STS\Models\User;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class UsersManagerTest extends TestCase
{
    private function manager(): UsersManager
    {
        return $this->app->make(UsersManager::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(string $email): array
    {
        return [
            'name' => 'Reg User '.substr($email, 0, 8),
            'email' => $email,
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
        ];
    }

    public function test_validator_create_requires_name_email_and_password_rules(): void
    {
        $v = $this->manager()->validator([], null, false, false, false);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('name'));
        $this->assertTrue($v->errors()->has('email'));

        $v2 = $this->manager()->validator([
            'name' => 'Ok Name',
            'email' => 'pw-'.uniqid('', true).'@example.com',
            'password' => 'short',
            'password_confirmation' => 'nomatch',
            'emails_notifications' => true,
        ], null, false, false, false);
        $this->assertTrue($v2->fails());
        $this->assertTrue($v2->errors()->has('password'));
    }

    public function test_validator_create_passes_with_confirmed_password(): void
    {
        $email = 'val-'.uniqid('', true).'@example.com';
        $v = $this->manager()->validator($this->validRegistrationPayload($email), null, false, false, false);
        $this->assertFalse($v->fails());
    }

    public function test_validator_update_includes_unique_email_rule_with_id(): void
    {
        $user = User::factory()->create();
        $v = $this->manager()->validator(['name' => 'Only'], $user->id, false, false, false);
        $rules = $v->getRules()['email'];
        $this->assertIsArray($rules);
        $this->assertStringContainsString((string) $user->id, implode('|', $rules));
    }

    public function test_validator_admin_omits_email_rule(): void
    {
        $user = User::factory()->create();
        $v = $this->manager()->validator(['name' => 'Admin touch'], $user->id, false, false, true);
        $this->assertArrayNotHasKey('email', $v->getRules());
    }

    public function test_validator_driver_requires_docs_when_module_enabled(): void
    {
        config(['carpoolear.module_validated_drivers' => true]);
        $v = $this->manager()->validator(
            $this->validRegistrationPayload('drv-'.uniqid('', true).'@example.com'),
            null,
            false,
            true,
            false
        );
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('driver_data_docs'));
        config(['carpoolear.module_validated_drivers' => false]);
    }

    public function test_find_delegates_to_repository_show(): void
    {
        $user = User::factory()->create();
        $found = $this->manager()->find($user->id);
        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function test_show_returns_null_when_profile_missing(): void
    {
        $viewer = User::factory()->create();
        $this->assertNull($this->manager()->show($viewer, 9_999_999_999));
    }

    public function test_create_persists_inactive_user_and_dispatches_create_event(): void
    {
        Event::fake([CreateEvent::class]);
        $email = 'new-'.uniqid('', true).'@example.com';
        $user = $this->manager()->create($this->validRegistrationPayload($email));

        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse((bool) $user->active);
        $this->assertNotNull($user->activation_token);
        Event::assertDispatched(CreateEvent::class, fn ($e) => (int) $e->id === (int) $user->id);
    }

    public function test_create_returns_null_when_validation_fails(): void
    {
        Event::fake([CreateEvent::class]);
        $manager = $this->manager();
        $this->assertNull($manager->create(['name' => 'x']));
        $this->assertTrue($manager->getErrors()->has('email'));
        Event::assertNotDispatched(CreateEvent::class);
    }

    public function test_update_allowed_field_persists_and_dispatches_update_event(): void
    {
        Event::fake([UpdateEvent::class]);
        $user = User::factory()->create(['description' => 'old']);
        $out = $this->manager()->update($user, ['description' => 'new profile text']);

        $this->assertInstanceOf(User::class, $out);
        $this->assertSame('new profile text', $user->fresh()->description);
        Event::assertDispatched(UpdateEvent::class);
    }

    public function test_update_rejects_banned_document_number(): void
    {
        $moderator = User::factory()->create();
        BannedUser::query()->create([
            'user_id' => $moderator->id,
            'nro_doc' => '30123456',
            'banned_at' => now(),
        ]);
        $user = User::factory()->create();

        $manager = $this->manager();
        $this->assertNull($manager->update($user, ['nro_doc' => '30.123.456']));
        $this->assertSame('banned_dni', $manager->getErrors()['error']);
    }

    public function test_admin_update_with_patente_creates_car_when_user_has_none(): void
    {
        Event::fake([UpdateEvent::class]);
        User::factory()->create(['is_admin' => 1]);
        $user = User::factory()->create();
        $this->assertSame(0, Car::query()->where('user_id', $user->id)->count());

        $updated = $this->manager()->update($user, [
            'patente' => 'AA123BB',
            'car_description' => 'Sedan gris',
        ], false, true);

        $this->assertInstanceOf(User::class, $updated);
        $car = Car::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($car);
        $this->assertSame('AA123BB', $car->patente);
        $this->assertSame('Sedan gris', $car->description);
        Event::assertDispatched(UpdateEvent::class);
    }

    public function test_admin_update_with_patente_updates_existing_car(): void
    {
        Event::fake([UpdateEvent::class]);
        $user = User::factory()->create();
        Car::query()->create([
            'user_id' => $user->id,
            'patente' => 'OLD111',
            'description' => 'Old description',
        ]);

        $updated = $this->manager()->update($user, [
            'patente' => 'NEW222',
            'car_description' => 'Updated description',
        ], false, true);

        $this->assertInstanceOf(User::class, $updated);
        $cars = Car::query()->where('user_id', $user->id)->get();
        $this->assertCount(1, $cars);
        $this->assertSame('NEW222', $cars->first()->patente);
        $this->assertSame('Updated description', $cars->first()->description);
        Event::assertDispatched(UpdateEvent::class);
    }

    public function test_active_account_activates_user_with_valid_token(): void
    {
        $user = User::factory()->create([
            'active' => false,
            'activation_token' => 'act-'.uniqid('', true),
        ]);

        $out = $this->manager()->activeAccount($user->activation_token);
        $this->assertNotNull($out);
        $this->assertTrue((bool) $out->fresh()->active);
        $this->assertNull($out->fresh()->activation_token);
    }

    public function test_active_account_sets_error_for_unknown_token(): void
    {
        $manager = $this->manager();
        $this->assertNull($manager->activeAccount('no-such-token'));
        $this->assertSame('invalid_activation_token', $manager->getErrors()['error']);
    }

    public function test_reset_password_queues_email_for_known_user(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $token = $this->manager()->resetPassword($user->email);

        $this->assertIsString($token);
        Queue::assertPushed(SendPasswordResetEmail::class);
    }

    public function test_reset_password_sets_error_for_unknown_email(): void
    {
        Queue::fake();
        $manager = $this->manager();
        $this->assertNull($manager->resetPassword('missing-'.uniqid('', true).'@example.com'));
        $this->assertSame('user_not_found', $manager->getErrors()['error']);
        Queue::assertNothingPushed();
    }

    public function test_change_password_updates_password_and_clears_token(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $token = $this->manager()->resetPassword($user->email);
        $this->assertNotNull($token);

        $manager = $this->manager();
        $this->assertTrue($manager->changePassword($token, [
            'email' => $user->email,
            'password' => 'newsecret12',
            'password_confirmation' => 'newsecret12',
        ]));

        $this->assertTrue(\Hash::check('newsecret12', $user->fresh()->password));
    }

    public function test_change_password_returns_null_for_invalid_token(): void
    {
        $manager = $this->manager();

        $this->assertNull($manager->changePassword('invalid-token', [
            'email' => 'x@example.com',
            'password' => 'newsecret12',
            'password_confirmation' => 'newsecret12',
        ]));
    }

    public function test_mail_unsubscribe_turns_off_email_notifications(): void
    {
        $user = User::factory()->create([
            'email' => 'unsub-'.uniqid('', true).'@example.com',
            'emails_notifications' => true,
        ]);

        $out = $this->manager()->mailUnsuscribe($user->email);
        $this->assertFalse((bool) $out->fresh()->emails_notifications);
    }

    public function test_search_users_finds_by_name_substring(): void
    {
        $needle = 'UniqueNm'.substr(uniqid('', true), 0, 8);
        User::factory()->create(['name' => $needle.' Full']);

        $rows = $this->manager()->searchUsers($needle);
        $this->assertGreaterThanOrEqual(1, $rows->count());
        $this->assertTrue($rows->pluck('name')->contains(fn ($n) => str_contains((string) $n, $needle)));
    }

    public function test_trips_count_returns_zero_without_finished_trips(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, $this->manager()->tripsCount($user));
        $this->assertSame(0, $this->manager()->tripsDistance($user));
    }

    public function test_register_donation_sets_user_and_month(): void
    {
        $user = User::factory()->create();
        $donation = new Donation([
            'has_donated' => true,
            'has_denied' => false,
            'ammount' => 500,
        ]);

        $saved = $this->manager()->registerDonation($user, $donation);
        $this->assertSame($user->id, (int) $saved->user_id);
        $this->assertNotNull($saved->month);
        $this->assertTrue($saved->exists);
    }

    public function test_update_photo_validation_requires_profile(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();
        $this->assertNull($manager->updatePhoto($user, []));
        $this->assertTrue($manager->getErrors()->has('profile'));
    }
}
