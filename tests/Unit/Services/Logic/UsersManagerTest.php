<?php

namespace Tests\Unit\Services\Logic;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use STS\Events\User\Create as CreateEvent;
use STS\Events\User\Update as UpdateEvent;
use STS\Jobs\SendPasswordResetEmail;
use STS\Models\BannedUser;
use STS\Models\Car;
use STS\Models\Donation;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripRepository;
use STS\Repository\UserRepository;
use STS\Services\Logic\UsersManager;
use STS\Services\UserEditablePropertiesService;
use Tests\TestCase;

class UsersManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

    public function test_validator_social_create_requires_email_key_to_be_present(): void
    {
        $validatorMissingEmail = $this->manager()->validator([
            'name' => 'Social User',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
        ], null, true, false, false);
        $this->assertTrue($validatorMissingEmail->fails());
        $this->assertTrue($validatorMissingEmail->errors()->has('email'));

        $validatorWithValidEmail = $this->manager()->validator([
            'name' => 'Social User',
            'email' => 'social-'.uniqid('', true).'@example.com',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
        ], null, true, false, false);
        $this->assertFalse($validatorWithValidEmail->fails());
    }

    public function test_validator_social_create_fails_with_invalid_email_format(): void
    {
        $validator = $this->manager()->validator([
            'name' => 'Social User',
            'email' => 'not-an-email',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('email'));
    }

    public function test_validator_social_create_fails_when_email_already_exists(): void
    {
        $existing = User::factory()->create();
        $validator = $this->manager()->validator([
            'name' => 'Social User',
            'email' => $existing->email,
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('email'));
    }

    public function test_validator_social_create_allows_missing_password_fields(): void
    {
        $validator = $this->manager()->validator([
            'name' => 'Social User',
            'email' => 'social-nopass-'.uniqid('', true).'@example.com',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertFalse($validator->fails());
    }

    public function test_validator_social_create_fails_when_name_is_missing(): void
    {
        $validator = $this->manager()->validator([
            'email' => 'social-noname-'.uniqid('', true).'@example.com',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    public function test_validator_social_create_fails_when_name_exceeds_max_length(): void
    {
        $validator = $this->manager()->validator([
            'name' => str_repeat('n', 256),
            'email' => 'social-longname-'.uniqid('', true).'@example.com',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    public function test_validator_social_create_accepts_name_with_exact_max_length(): void
    {
        $validator = $this->manager()->validator([
            'name' => str_repeat('n', 255),
            'email' => 'social-maxname-'.uniqid('', true).'@example.com',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertFalse($validator->fails());
    }

    public function test_validator_social_create_fails_with_empty_name_string(): void
    {
        $validator = $this->manager()->validator([
            'name' => '',
            'email' => 'social-emptyname-'.uniqid('', true).'@example.com',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    public function test_validator_social_create_fails_when_emails_notifications_is_not_boolean(): void
    {
        $validator = $this->manager()->validator([
            'name' => 'Social User',
            'email' => 'social-bool-'.uniqid('', true).'@example.com',
            'emails_notifications' => ['invalid'],
        ], null, true, false, false);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('emails_notifications'));
    }

    public function test_validator_social_create_allows_empty_email_string_when_key_is_present(): void
    {
        $validator = $this->manager()->validator([
            'name' => 'Social User',
            'email' => '',
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
        ], null, true, false, false);

        $this->assertFalse($validator->fails());
    }

    public function test_validator_update_includes_unique_email_rule_with_id(): void
    {
        $user = User::factory()->create();
        $v = $this->manager()->validator(['name' => 'Only'], $user->id, false, false, false);
        $rules = $v->getRules()['email'];
        $this->assertIsArray($rules);
        $this->assertStringContainsString((string) $user->id, implode('|', $rules));
    }

    public function test_validator_update_adds_unique_doc_and_phone_rules_when_feature_enabled_for_non_admin(): void
    {
        config(['carpoolear.module_unique_doc_phone' => true]);
        $user = User::factory()->create();

        $validator = $this->manager()->validator([
            'nro_doc' => '30111222',
            'mobile_phone' => '+5491122223333',
        ], $user->id, false, false, false);

        $rules = $validator->getRules();
        $this->assertArrayHasKey('nro_doc', $rules);
        $this->assertArrayHasKey('mobile_phone', $rules);
        $this->assertStringContainsString('unique:users,nro_doc,'.$user->id, implode('|', $rules['nro_doc']));
        $this->assertStringContainsString('unique:users,mobile_phone,'.$user->id, implode('|', $rules['mobile_phone']));

        config(['carpoolear.module_unique_doc_phone' => false]);
    }

    public function test_validator_update_does_not_add_unique_doc_and_phone_rules_when_feature_disabled(): void
    {
        config(['carpoolear.module_unique_doc_phone' => false]);
        $user = User::factory()->create();

        $validator = $this->manager()->validator([
            'nro_doc' => '30111222',
            'mobile_phone' => '+5491122223333',
        ], $user->id, false, false, false);

        $rules = $validator->getRules();
        $this->assertArrayNotHasKey('nro_doc', $rules);
        $this->assertArrayNotHasKey('mobile_phone', $rules);
    }

    public function test_validator_update_does_not_add_unique_doc_and_phone_rules_for_admin(): void
    {
        config(['carpoolear.module_unique_doc_phone' => true]);
        $user = User::factory()->create();

        $validator = $this->manager()->validator([
            'nro_doc' => '30111222',
            'mobile_phone' => '+5491122223333',
        ], $user->id, false, false, true);

        $rules = $validator->getRules();
        $this->assertArrayNotHasKey('nro_doc', $rules);
        $this->assertArrayNotHasKey('mobile_phone', $rules);

        config(['carpoolear.module_unique_doc_phone' => false]);
    }

    public function test_validator_admin_omits_email_rule(): void
    {
        $user = User::factory()->create();
        $v = $this->manager()->validator(['name' => 'Admin touch'], $user->id, false, false, true);
        $this->assertArrayNotHasKey('email', $v->getRules());
    }

    public function test_validator_admin_adds_patente_and_car_description_rules_when_patente_present(): void
    {
        $user = User::factory()->create();
        $v = $this->manager()->validator([
            'name' => 'Admin touch',
            'patente' => 'AA123BB',
            'car_description' => 'Sedan gris',
        ], $user->id, false, false, true);

        $rules = $v->getRules();
        $this->assertArrayHasKey('patente', $rules);
        $this->assertArrayHasKey('car_description', $rules);
        $this->assertStringContainsString('max:10', implode('|', $rules['patente']));
        $this->assertStringContainsString('nullable', implode('|', $rules['car_description']));
    }

    public function test_validator_admin_fails_when_patente_exceeds_max_length(): void
    {
        $user = User::factory()->create();
        $validator = $this->manager()->validator([
            'name' => 'Admin touch',
            'patente' => 'ABC12345678',
        ], $user->id, false, false, true);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('patente'));
    }

    public function test_validator_admin_accepts_patente_with_exact_max_length(): void
    {
        $user = User::factory()->create();
        $validator = $this->manager()->validator([
            'name' => 'Admin touch',
            'patente' => 'ABC1234567',
        ], $user->id, false, false, true);

        $this->assertFalse($validator->fails());
    }

    public function test_validator_admin_fails_when_car_description_exceeds_max_length(): void
    {
        $user = User::factory()->create();
        $validator = $this->manager()->validator([
            'name' => 'Admin touch',
            'patente' => 'AA123BB',
            'car_description' => str_repeat('x', 256),
        ], $user->id, false, false, true);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('car_description'));
    }

    public function test_validator_admin_accepts_car_description_with_exact_max_length(): void
    {
        $user = User::factory()->create();
        $validator = $this->manager()->validator([
            'name' => 'Admin touch',
            'patente' => 'AA123BB',
            'car_description' => str_repeat('x', 255),
        ], $user->id, false, false, true);

        $this->assertFalse($validator->fails());
    }

    public function test_validator_admin_does_not_add_car_rules_when_patente_is_missing(): void
    {
        $user = User::factory()->create();
        $v = $this->manager()->validator([
            'name' => 'Admin touch',
            'car_description' => 'Only description',
        ], $user->id, false, false, true);

        $rules = $v->getRules();
        $this->assertArrayNotHasKey('patente', $rules);
        $this->assertArrayNotHasKey('car_description', $rules);
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

    public function test_show_returns_profile_when_it_exists(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create();

        $result = $this->manager()->show($viewer, $profile->id);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($profile->id, $result->id);
    }

    public function test_show_sets_profile_not_found_error_when_profile_missing(): void
    {
        $viewer = User::factory()->create();
        $manager = $this->manager();

        $result = $manager->show($viewer, 9_999_999_999);

        $this->assertNull($result);
        $this->assertSame('profile not found', $manager->getErrors()['error']);
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

    public function test_create_bans_user_when_name_contains_configured_banned_word(): void
    {
        Event::fake([CreateEvent::class]);
        config(['carpoolear.banned_words_names' => ['forbiddenword']]);
        $email = 'banned-name-'.uniqid('', true).'@example.com';

        $user = $this->manager()->create([
            'name' => 'User ForbiddenWord Name',
            'email' => $email,
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(1, (int) $user->fresh()->banned);
        Event::assertDispatched(CreateEvent::class, fn ($e) => (int) $e->id === (int) $user->id);

        config(['carpoolear.banned_words_names' => []]);
    }

    public function test_create_forces_email_notifications_to_true_even_when_payload_sets_false(): void
    {
        Event::fake([CreateEvent::class]);
        $email = 'notif-force-'.uniqid('', true).'@example.com';

        $user = $this->manager()->create([
            'name' => 'Notifications Forced User',
            'email' => $email,
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => false,
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue((bool) $user->fresh()->emails_notifications);
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

    public function test_create_can_bypass_validation_when_validate_flag_is_false(): void
    {
        Event::fake([CreateEvent::class]);

        $user = $this->manager()->create([
            'name' => 'No Validate User',
            'email' => 'novalidate-'.uniqid('', true).'@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'different-confirmation',
        ], false);

        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse((bool) $user->active);
        $this->assertNotNull($user->activation_token);
        Event::assertDispatched(CreateEvent::class, fn ($e) => (int) $e->id === (int) $user->id);
    }

    public function test_create_returns_null_when_active_is_preset_without_token(): void
    {
        Event::fake([CreateEvent::class]);
        $email = 'preset-active-'.uniqid('', true).'@example.com';

        $result = $this->manager()->create([
            'name' => 'Preset Active User',
            'email' => $email,
            'password' => 'password12',
            'password_confirmation' => 'password12',
            'emails_notifications' => true,
            'active' => true,
        ]);

        $this->assertNull($result);
        $this->assertNull(User::query()->where('email', $email)->first());
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

    public function test_update_hides_trips_when_user_is_banned(): void
    {
        Event::fake([UpdateEvent::class]);
        $user = User::factory()->make([
            'id' => 98765,
            'name' => 'Banned User',
            'banned' => 1,
        ]);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('update')
            ->once()
            ->with($user, Mockery::on(fn ($data) => ($data['description'] ?? null) === 'updated'))
            ->andReturnUsing(function ($targetUser, $data) {
                $targetUser->description = $data['description'];

                return $targetUser;
            });

        $tripRepo = Mockery::mock(TripRepository::class);
        $tripRepo->shouldReceive('hideTrips')->once()->with($user);
        $tripRepo->shouldReceive('unhideTrips')->never();

        $editableService = Mockery::mock(UserEditablePropertiesService::class);
        $editableService->shouldReceive('filterForUser')
            ->once()
            ->with(Mockery::type('array'), false)
            ->andReturnUsing(fn ($data) => $data);
        $editableService->shouldReceive('getBlockedFlaggedPropertiesThatDiffer')
            ->once()
            ->with($user, Mockery::type('array'), Mockery::type('array'), false)
            ->andReturn([]);
        $editableService->shouldReceive('sendFlaggedPropertyAlert')->never();

        $manager = new UsersManager($userRepo, $tripRepo, null, $editableService);
        $result = $manager->update($user, ['description' => 'updated']);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('updated', $result->description);
        Event::assertDispatched(UpdateEvent::class);
    }

    public function test_update_unhides_trips_when_user_is_not_banned(): void
    {
        Event::fake([UpdateEvent::class]);
        $user = User::factory()->make([
            'id' => 87654,
            'name' => 'Allowed User',
            'banned' => 0,
        ]);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('update')
            ->once()
            ->with($user, Mockery::on(fn ($data) => ($data['description'] ?? null) === 'updated allowed'))
            ->andReturnUsing(function ($targetUser, $data) {
                $targetUser->description = $data['description'];

                return $targetUser;
            });

        $tripRepo = Mockery::mock(TripRepository::class);
        $tripRepo->shouldReceive('hideTrips')->never();
        $tripRepo->shouldReceive('unhideTrips')->once()->with($user);

        $editableService = Mockery::mock(UserEditablePropertiesService::class);
        $editableService->shouldReceive('filterForUser')
            ->once()
            ->with(Mockery::type('array'), false)
            ->andReturnUsing(fn ($data) => $data);
        $editableService->shouldReceive('getBlockedFlaggedPropertiesThatDiffer')
            ->once()
            ->with($user, Mockery::type('array'), Mockery::type('array'), false)
            ->andReturn([]);
        $editableService->shouldReceive('sendFlaggedPropertyAlert')->never();

        $manager = new UsersManager($userRepo, $tripRepo, null, $editableService);
        $result = $manager->update($user, ['description' => 'updated allowed']);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('updated allowed', $result->description);
        Event::assertDispatched(UpdateEvent::class);
    }

    public function test_update_sends_flagged_property_alert_and_still_updates_allowed_fields(): void
    {
        Event::fake([UpdateEvent::class]);
        $user = User::factory()->make([
            'id' => 76543,
            'name' => 'Flagged User',
            'banned' => 0,
        ]);
        $requestData = [
            'description' => 'safe description',
            'is_admin' => 1,
        ];
        $filteredData = [
            'description' => 'safe description',
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('update')
            ->once()
            ->with($user, $filteredData)
            ->andReturnUsing(function ($targetUser, $data) {
                $targetUser->description = $data['description'];

                return $targetUser;
            });

        $tripRepo = Mockery::mock(TripRepository::class);
        $tripRepo->shouldReceive('hideTrips')->never();
        $tripRepo->shouldReceive('unhideTrips')->once()->with($user);

        $editableService = Mockery::mock(UserEditablePropertiesService::class);
        $editableService->shouldReceive('filterForUser')
            ->once()
            ->with($requestData, false)
            ->andReturn($filteredData);
        $editableService->shouldReceive('getBlockedFlaggedPropertiesThatDiffer')
            ->once()
            ->with($user, $requestData, $filteredData, false)
            ->andReturn(['is_admin']);
        $editableService->shouldReceive('sendFlaggedPropertyAlert')
            ->once()
            ->with($user, ['is_admin']);

        $manager = new UsersManager($userRepo, $tripRepo, null, $editableService);
        $result = $manager->update($user, $requestData);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('safe description', $result->description);
        Event::assertDispatched(UpdateEvent::class);
    }

    public function test_admin_update_does_not_apply_banned_dni_rejection(): void
    {
        Event::fake([UpdateEvent::class]);
        $user = User::factory()->make([
            'id' => 65432,
            'name' => 'Admin Updated User',
            'banned' => 0,
        ]);
        $requestData = [
            'nro_doc' => '30.123.456',
            'description' => 'admin updated',
        ];

        BannedUser::query()->create([
            'user_id' => User::factory()->create()->id,
            'nro_doc' => '30123456',
            'banned_at' => now(),
        ]);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('update')
            ->once()
            ->with($user, $requestData)
            ->andReturnUsing(function ($targetUser, $data) {
                $targetUser->nro_doc = $data['nro_doc'];
                $targetUser->description = $data['description'];

                return $targetUser;
            });

        $tripRepo = Mockery::mock(TripRepository::class);
        $tripRepo->shouldReceive('hideTrips')->never();
        $tripRepo->shouldReceive('unhideTrips')->once()->with($user);

        $editableService = Mockery::mock(UserEditablePropertiesService::class);
        $editableService->shouldReceive('filterForUser')
            ->once()
            ->with($requestData, true)
            ->andReturn($requestData);
        $editableService->shouldReceive('getBlockedFlaggedPropertiesThatDiffer')
            ->once()
            ->with($user, $requestData, $requestData, true)
            ->andReturn([]);
        $editableService->shouldReceive('sendFlaggedPropertyAlert')->never();

        $manager = new UsersManager($userRepo, $tripRepo, null, $editableService);
        $result = $manager->update($user, $requestData, false, true);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('30.123.456', $result->nro_doc);
        $this->assertSame('admin updated', $result->description);
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

    public function test_update_rejects_banned_document_number_and_attempts_slack_webhook(): void
    {
        Http::fake([
            'https://hooks.slack.test/*' => Http::response(['ok' => true], 200),
        ]);
        config(['services.slack.banned_dni_webhook_url' => 'https://hooks.slack.test/services/T000/B000/XYZ']);

        $moderator = User::factory()->create();
        BannedUser::query()->create([
            'user_id' => $moderator->id,
            'nro_doc' => '30999888',
            'banned_at' => now(),
        ]);
        $user = User::factory()->create();

        $manager = $this->manager();
        $result = $manager->update($user, ['nro_doc' => '30.999.888']);

        $this->assertNull($result);
        $this->assertSame('banned_dni', $manager->getErrors()['error']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($user) {
            return str_contains($request->url(), 'https://hooks.slack.test/services/')
                && str_contains((string) $request->body(), (string) $user->id);
        });
    }

    public function test_update_rejects_banned_document_number_when_webhook_returns_error_response(): void
    {
        Http::fake([
            'https://hooks.slack.test/*' => Http::response(['ok' => false], 500),
        ]);
        config(['services.slack.banned_dni_webhook_url' => 'https://hooks.slack.test/services/T000/B000/XYZ']);

        $moderator = User::factory()->create();
        BannedUser::query()->create([
            'user_id' => $moderator->id,
            'nro_doc' => '30000111',
            'banned_at' => now(),
        ]);
        $user = User::factory()->create();

        $manager = $this->manager();
        $result = $manager->update($user, ['nro_doc' => '30.000.111']);

        $this->assertNull($result);
        $this->assertSame('banned_dni', $manager->getErrors()['error']);
        Http::assertSentCount(1);
    }

    public function test_update_rejects_banned_document_number_when_webhook_throws_exception(): void
    {
        Http::fake([
            'https://hooks.slack.test/*' => function () {
                throw new \RuntimeException('Webhook failed');
            },
        ]);
        config(['services.slack.banned_dni_webhook_url' => 'https://hooks.slack.test/services/T000/B000/XYZ']);

        $moderator = User::factory()->create();
        BannedUser::query()->create([
            'user_id' => $moderator->id,
            'nro_doc' => '30000999',
            'banned_at' => now(),
        ]);
        $user = User::factory()->create();

        $manager = $this->manager();
        $result = $manager->update($user, ['nro_doc' => '30.000.999']);

        $this->assertNull($result);
        $this->assertSame('banned_dni', $manager->getErrors()['error']);
    }

    public function test_update_rejects_banned_document_number_without_webhook_config_and_skips_http_call(): void
    {
        Http::fake();
        config(['services.slack.banned_dni_webhook_url' => null]);

        $moderator = User::factory()->create();
        BannedUser::query()->create([
            'user_id' => $moderator->id,
            'nro_doc' => '30777111',
            'banned_at' => now(),
        ]);
        $user = User::factory()->create();

        $manager = $this->manager();
        $result = $manager->update($user, ['nro_doc' => '30.777.111']);

        $this->assertNull($result);
        $this->assertSame('banned_dni', $manager->getErrors()['error']);
        Http::assertNothingSent();
    }

    public function test_update_with_blank_document_number_skips_banned_check_and_updates_user(): void
    {
        Http::fake();
        $moderator = User::factory()->create();
        BannedUser::query()->create([
            'user_id' => $moderator->id,
            'nro_doc' => '30123123',
            'banned_at' => now(),
        ]);
        $user = User::factory()->create([
            'description' => 'old description',
            'nro_doc' => '12345678',
        ]);

        $manager = $this->manager();
        $result = $manager->update($user, [
            'nro_doc' => '   ',
            'description' => 'updated description',
        ]);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('updated description', $user->fresh()->description);
        $this->assertNull($manager->getErrors());
        Http::assertNothingSent();
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

    public function test_admin_update_with_only_car_description_does_not_create_new_car(): void
    {
        Event::fake([UpdateEvent::class]);
        $user = User::factory()->create();
        $this->assertSame(0, Car::query()->where('user_id', $user->id)->count());

        $updated = $this->manager()->update($user, [
            'car_description' => 'Description without patente',
        ], false, true);

        $this->assertInstanceOf(User::class, $updated);
        $this->assertSame(0, Car::query()->where('user_id', $user->id)->count());
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

    public function test_reset_password_enforces_cooldown_between_requests(): void
    {
        Queue::fake();
        $user = User::factory()->make([
            'id' => 12345,
            'email' => 'cooldown@example.com',
        ]);
        $lastReset = (object) ['created_at' => now()];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getUserBy')->twice()->with('email', $user->email)->andReturn($user);
        $userRepo->shouldReceive('getLastPasswordReset')->twice()->with($user->email)->andReturn(null, $lastReset);
        $userRepo->shouldReceive('deleteResetToken')->once();
        $userRepo->shouldReceive('storeResetToken')->once();
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $firstToken = $manager->resetPassword($user->email);
        $this->assertIsString($firstToken);

        $secondToken = $manager->resetPassword($user->email);
        $this->assertNull($secondToken);
        $this->assertStringContainsString('Please wait', $manager->getErrors()['error']);

        Queue::assertPushed(SendPasswordResetEmail::class, 1);
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

    public function test_change_password_sets_validation_errors_when_confirmation_mismatches(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $token = $this->manager()->resetPassword($user->email);
        $this->assertNotNull($token);

        $manager = $this->manager();
        $result = $manager->changePassword($token, [
            'email' => $user->email,
            'password' => 'newsecret12',
            'password_confirmation' => 'different-password',
        ]);

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('password'));
        $this->assertFalse(\Hash::check('newsecret12', $user->fresh()->password));
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

    public function test_index_returns_users_matching_search_text(): void
    {
        $viewer = User::factory()->create();
        $needle = 'IdxNm'.substr(uniqid('', true), 0, 8);
        $matching = User::factory()->create(['name' => $needle.' Match']);
        User::factory()->create(['name' => 'Completely Different']);

        $rows = $this->manager()->index($viewer, $needle);

        $this->assertTrue($rows->pluck('id')->contains($matching->id));
        $this->assertFalse($rows->pluck('name')->contains('Completely Different'));
    }

    public function test_trips_count_returns_zero_without_finished_trips(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, $this->manager()->tripsCount($user));
        $this->assertSame(0, $this->manager()->tripsDistance($user));
    }

    public function test_trips_count_and_distance_include_finished_driver_and_passenger_trips(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => now()->subDay(),
            'distance' => 10000,
        ]);
        $passengerTrip = Trip::factory()->create([
            'trip_date' => now()->subDays(2),
            'distance' => 25000,
        ]);
        Passenger::factory()->create([
            'trip_id' => $passengerTrip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_ACCEPTED,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->assertSame(2, $this->manager()->tripsCount($user));
        $this->assertSame(35000, (int) $this->manager()->tripsDistance($user));
        $this->assertSame(1, $this->manager()->tripsCount($user, Passenger::TYPE_CONDUCTOR));
        $this->assertSame(1, $this->manager()->tripsCount($user, Passenger::TYPE_PASAJERO));
        $this->assertSame(10000, (int) $this->manager()->tripsDistance($user, Passenger::TYPE_CONDUCTOR));
        $this->assertSame(25000, (int) $this->manager()->tripsDistance($user, Passenger::TYPE_PASAJERO));
    }

    public function test_trips_metrics_return_zero_for_unsupported_passenger_type(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => now()->subDay(),
            'distance' => 10000,
        ]);

        $this->assertSame(0, $this->manager()->tripsCount($user, 9999));
        $this->assertSame(0, (int) $this->manager()->tripsDistance($user, 9999));
    }

    public function test_trips_metrics_ignore_future_driver_and_passenger_trips(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => now()->addDay(),
            'distance' => 10000,
        ]);
        $futurePassengerTrip = Trip::factory()->create([
            'trip_date' => now()->addDays(2),
            'distance' => 25000,
        ]);
        Passenger::factory()->create([
            'trip_id' => $futurePassengerTrip->id,
            'user_id' => $user->id,
            'request_state' => Passenger::STATE_ACCEPTED,
            'passenger_type' => Passenger::TYPE_PASAJERO,
        ]);

        $this->assertSame(0, $this->manager()->tripsCount($user));
        $this->assertSame(0, (int) $this->manager()->tripsDistance($user));
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

    public function test_unanswered_conversation_or_requests_by_trip_respects_user_limit(): void
    {
        $trip = (object) [
            'id' => 99,
            'user_id' => 7,
            'user' => (object) ['unaswered_messages_limit' => 3],
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(7, 99)
            ->andReturn(3);
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $this->assertFalse($manager->unansweredConversationOrRequestsByTrip($trip));
    }

    public function test_unanswered_conversation_or_requests_by_trip_allows_when_limit_is_not_configured(): void
    {
        $trip = (object) [
            'id' => 100,
            'user_id' => 8,
            'user' => (object) ['unaswered_messages_limit' => null],
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(8, 100)
            ->andReturn(999);
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $this->assertTrue($manager->unansweredConversationOrRequestsByTrip($trip));
    }

    public function test_unanswered_conversation_or_requests_by_trip_allows_when_count_is_below_limit(): void
    {
        $trip = (object) [
            'id' => 101,
            'user_id' => 9,
            'user' => (object) ['unaswered_messages_limit' => 4],
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(9, 101)
            ->andReturn(3);
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $this->assertTrue($manager->unansweredConversationOrRequestsByTrip($trip));
    }

    public function test_unanswered_conversation_or_requests_by_trip_allows_when_limit_is_zero(): void
    {
        $trip = (object) [
            'id' => 102,
            'user_id' => 10,
            'user' => (object) ['unaswered_messages_limit' => 0],
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(10, 102)
            ->andReturn(999);
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $this->assertTrue($manager->unansweredConversationOrRequestsByTrip($trip));
    }

    public function test_unanswered_conversation_or_requests_by_trip_blocks_when_count_is_above_limit(): void
    {
        $trip = (object) [
            'id' => 103,
            'user_id' => 11,
            'user' => (object) ['unaswered_messages_limit' => 4],
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(11, 103)
            ->andReturn(5);
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $this->assertFalse($manager->unansweredConversationOrRequestsByTrip($trip));
    }

    public function test_unanswered_conversation_or_requests_by_trip_allows_when_limit_is_negative(): void
    {
        $trip = (object) [
            'id' => 105,
            'user_id' => 13,
            'user' => (object) ['unaswered_messages_limit' => -1],
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(13, 105)
            ->andReturn(999);
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $this->assertTrue($manager->unansweredConversationOrRequestsByTrip($trip));
    }

    public function test_unanswered_conversation_or_requests_by_trip_throws_when_limit_property_is_missing(): void
    {
        $trip = (object) [
            'id' => 104,
            'user_id' => 12,
            'user' => (object) [],
        ];

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('unansweredConversationOrRequestsByTrip')
            ->once()
            ->with(12, 104)
            ->andReturn(999);
        $tripRepo = Mockery::mock(TripRepository::class);
        $manager = new UsersManager($userRepo, $tripRepo);

        $this->expectException(\ErrorException::class);
        $manager->unansweredConversationOrRequestsByTrip($trip);
    }

    public function test_update_photo_validation_requires_profile(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();
        $this->assertNull($manager->updatePhoto($user, []));
        $this->assertTrue($manager->getErrors()->has('profile'));
    }

    public function test_update_photo_sets_error_when_profile_payload_is_not_base64_data_uri(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->updatePhoto($user, [
            'profile' => 'invalid-base64-payload',
        ]));

        $errors = $manager->getErrors();
        $this->assertIsObject($errors);
        $this->assertSame('error_uploading_image', $errors->error);
    }
}
