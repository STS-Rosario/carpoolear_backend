<?php

namespace Tests\Unit\Transformers;

use STS\Models\Car;
use STS\Models\Passenger;
use STS\Models\Rating;
use STS\Models\SupportTicket;
use STS\Models\Trip;
use STS\Models\User;
use STS\Transformers\ProfileTransformer;
use Tests\TestCase;

class ProfileTransformerTest extends TestCase
{
    /**
     * Must match the root keys emitted by the initial $data block in ProfileTransformer::transform
     * (order matters for RemoveArrayItem mutants).
     *
     * @return list<string>
     */
    private function expectedPublicProfileRootKeys(): array
    {
        return [
            'id',
            'name',
            'badges',
            'description',
            'private_note',
            'image',
            'positive_ratings',
            'negative_ratings',
            'birthday',
            'gender',
            'last_connection',
            'accounts',
            'donations',
            'has_pin',
            'is_member',
            'banned',
            'active',
            'do_not_alert_request_seat',
            'do_not_alert_accept_passenger',
            'do_not_alert_pending_rates',
            'do_not_alert_pricing',
            'monthly_donate',
            'unaswered_messages_limit',
            'autoaccept_requests',
            'driver_is_verified',
            'driver_data_docs',
            'references',
            'data_visibility',
            'facebook_profile_url',
            'references_data',
            'identity_validated',
            'identity_validated_at',
            'identity_validation_type',
        ];
    }

    public function test_transform_public_payload_root_key_list_matches_transformer_contract(): void
    {
        $user = User::factory()->create([
            'name' => 'Key Contract User',
            'data_visibility' => '2',
        ]);
        $user->forceFill([
            'last_connection' => null,
            'driver_data_docs' => null,
            'identity_validated' => 0,
            'identity_validated_at' => null,
            'identity_validation_type' => null,
        ])->saveQuietly();

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertSame($this->expectedPublicProfileRootKeys(), array_keys($payload));
    }

    public function test_transform_includes_expected_public_profile_fields_and_serializes_last_connection(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
            'image' => 'profile.png',
            'last_connection' => '2025-06-01 12:00:00',
        ]);
        $user->forceFill([
            'description' => 'About me',
            'private_note' => 'Private',
            'birthday' => '1990-01-01',
            'gender' => 'f',
            'has_pin' => 1,
            'is_member' => 0,
            'banned' => 0,
            'active' => 1,
            'monthly_donate' => 0,
            'do_not_alert_request_seat' => 1,
            'do_not_alert_accept_passenger' => 0,
            'do_not_alert_pending_rates' => 1,
            'do_not_alert_pricing' => 0,
            'unaswered_messages_limit' => 0,
            'autoaccept_requests' => 0,
            'driver_is_verified' => 0,
            'driver_data_docs' => null,
            'data_visibility' => '2',
            'identity_validated' => 0,
            'identity_validated_at' => null,
            'identity_validation_type' => null,
        ])->saveQuietly();

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertArrayHasKey('badges', $payload);
        $this->assertArrayHasKey('private_note', $payload);
        $this->assertArrayHasKey('image', $payload);
        $this->assertArrayHasKey('positive_ratings', $payload);
        $this->assertArrayHasKey('negative_ratings', $payload);
        $this->assertArrayHasKey('birthday', $payload);
        $this->assertArrayHasKey('gender', $payload);
        $this->assertArrayHasKey('last_connection', $payload);
        $this->assertArrayHasKey('accounts', $payload);
        $this->assertArrayHasKey('donations', $payload);
        $this->assertArrayHasKey('has_pin', $payload);
        $this->assertArrayHasKey('is_member', $payload);
        $this->assertArrayHasKey('banned', $payload);
        $this->assertArrayHasKey('active', $payload);
        $this->assertArrayHasKey('do_not_alert_request_seat', $payload);
        $this->assertArrayHasKey('do_not_alert_accept_passenger', $payload);
        $this->assertArrayHasKey('do_not_alert_pending_rates', $payload);
        $this->assertArrayHasKey('do_not_alert_pricing', $payload);

        $this->assertSame('Private', $payload['private_note']);
        $this->assertSame('profile.png', $payload['image']);
        $this->assertSame(0, $payload['positive_ratings']);
        $this->assertSame(0, $payload['negative_ratings']);
        $this->assertSame('2025-06-01 12:00:00', $payload['last_connection']);
    }

    public function test_transform_last_connection_serializes_to_empty_string_when_null(): void
    {
        $user = User::factory()->create(['data_visibility' => '2']);
        $user->forceFill(['last_connection' => null])->saveQuietly();

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertSame('', $payload['last_connection']);
    }

    public function test_transform_driver_data_docs_json_decodes_when_present(): void
    {
        $user = User::factory()->create(['data_visibility' => '2']);
        $user->forceFill([
            'driver_data_docs' => json_encode(['license' => 'ok']),
        ])->saveQuietly();

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertIsObject($payload['driver_data_docs']);
        $this->assertSame('ok', $payload['driver_data_docs']->license);
    }

    public function test_transform_identity_validated_is_strict_boolean(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['identity_validated' => true])->saveQuietly();

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertSame(true, $payload['identity_validated']);
        $this->assertIsBool($payload['identity_validated']);
    }

    public function test_transform_identity_validated_false_is_strict_boolean(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['identity_validated' => false])->saveQuietly();

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertSame(false, $payload['identity_validated']);
        $this->assertIsBool($payload['identity_validated']);
    }

    public function test_transform_includes_private_fields_when_viewing_own_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'self-profile@example.test',
            'mobile_phone' => '+5491112345678',
        ]);

        $payload = (new ProfileTransformer($user))->transform($user->fresh());

        $this->assertSame('self-profile@example.test', $payload['email']);
        $this->assertSame('+5491112345678', $payload['mobile_phone']);
        $this->assertArrayHasKey('validate_by_date', $payload);
    }

    public function test_transform_own_profile_branch_uses_loose_id_equality_for_string_subject_id(): void
    {
        $user = User::factory()->create(['email' => 'self@example.test']);
        $subject = $user->fresh();
        $subject->mergeCasts(['id' => 'string']);
        $subject->syncOriginal();
        $subject->forceFill(['id' => (string) $user->id]);

        $payload = (new ProfileTransformer($user))->transform($subject);

        $this->assertArrayHasKey('identity_validation_required_for_user', $payload);
        $this->assertSame('self@example.test', $payload['email']);
    }

    public function test_transform_includes_sensitive_fields_for_admin_viewing_other_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subject = User::factory()->create([
            'email' => 'subject@example.test',
            'nro_doc' => '30123456',
        ]);

        $payload = (new ProfileTransformer($admin))->transform($subject->fresh());

        $this->assertSame('subject@example.test', $payload['email']);
        $this->assertSame('30123456', $payload['nro_doc']);
    }

    public function test_transform_exposes_created_at_for_admin_viewing_other_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subject = User::factory()->create([
            'email' => 'with-created-at@example.test',
            'created_at' => '2024-03-15 12:34:56',
        ]);

        $payload = (new ProfileTransformer($admin))->transform($subject->fresh());

        $this->assertArrayHasKey('created_at', $payload);
        $this->assertSame('2024-03-15 12:34:56', $payload['created_at']);
    }

    public function test_transform_does_not_expose_created_at_for_non_admin_viewing_other_user(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);
        $subject = User::factory()->create(['data_visibility' => '2']);

        $payload = (new ProfileTransformer($viewer))->transform($subject->fresh());

        $this->assertArrayNotHasKey('created_at', $payload);
    }

    public function test_transform_admin_branch_uses_loose_id_equality_for_string_subject_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subject = User::factory()->create(['email' => 'subj@example.test']);
        $s = $subject->fresh();
        $s->mergeCasts(['id' => 'string']);
        $s->syncOriginal();
        $s->forceFill(['id' => (string) $subject->id]);

        $payload = (new ProfileTransformer($admin))->transform($s);

        $this->assertSame('subj@example.test', $payload['email']);
    }

    public function test_transform_viaja_conmigo_adds_contact_when_accepted_passenger_views_driver(): void
    {
        $driver = User::factory()->create([
            'data_visibility' => '0',
            'nro_doc' => '11111111',
            'email' => 'driver@example.test',
            'mobile_phone' => '+541111111111',
        ]);
        $viewer = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => now()->addDay()->startOfDay(),
            'weekly_schedule' => 0,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);
        Passenger::query()->create([
            'user_id' => $viewer->id,
            'trip_id' => $trip->id,
            'passenger_type' => Passenger::TYPE_PASAJERO,
            'request_state' => Passenger::STATE_ACCEPTED,
            'canceled_state' => null,
        ]);

        $payload = (new ProfileTransformer($viewer))->transform($driver->fresh());

        $this->assertSame('11111111', $payload['nro_doc']);
        $this->assertSame('driver@example.test', $payload['email']);
        $this->assertSame('+541111111111', $payload['mobile_phone']);
    }

    public function test_transform_viaja_conmigo_hides_contact_when_viewer_has_no_shared_accepted_trip(): void
    {
        $driver = User::factory()->create([
            'data_visibility' => '0',
            'nro_doc' => '99999999',
            'email' => 'hidden@example.test',
        ]);
        $stranger = User::factory()->create();

        $payload = (new ProfileTransformer($stranger))->transform($driver->fresh());

        $this->assertArrayNotHasKey('nro_doc', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    public function test_transform_public_visibility_one_exposes_contact_without_viewer_context(): void
    {
        $user = User::factory()->create([
            'data_visibility' => '1',
            'nro_doc' => '22222222',
            'email' => 'public@example.test',
        ]);

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertSame('22222222', $payload['nro_doc']);
        $this->assertSame('public@example.test', $payload['email']);
    }

    public function test_transform_unknown_data_visibility_does_not_expose_public_contact_block(): void
    {
        $user = User::factory()->create([
            'data_visibility' => '9',
            'nro_doc' => '33333333',
            'email' => 'privatevis@example.test',
        ]);

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertArrayNotHasKey('nro_doc', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    public function test_transform_does_not_emit_state_key_when_user_state_is_empty(): void
    {
        $user = User::factory()->create(['data_visibility' => '2']);

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertArrayNotHasKey('state', $payload);
    }

    public function test_transform_cars_for_own_profile_excludes_soft_deleted(): void
    {
        $user = User::factory()->create(['data_visibility' => '2']);
        Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'ACT123',
            'description' => 'Active',
        ]);
        $deleted = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'DEL456',
            'description' => 'Removed',
        ]);
        $deleted->delete();

        $payload = (new ProfileTransformer($user))->transform($user->fresh(['cars']));

        $this->assertArrayHasKey('cars', $payload);
        $patentes = collect($payload['cars'])->pluck('patente')->all();
        $this->assertSame(['ACT123'], $patentes);
    }

    public function test_transform_includes_support_tickets_count_for_admin_viewing_other_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subject = User::factory()->create();

        SupportTicket::create([
            'user_id' => $subject->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => 'Open',
            'priority' => 'normal',
        ]);

        $payload = (new ProfileTransformer($admin))->transform($subject->fresh());

        $this->assertArrayHasKey('support_tickets_count', $payload);
        $this->assertSame(1, $payload['support_tickets_count']);
    }

    public function test_transform_does_not_expose_support_tickets_count_for_non_admin_viewing_other_user(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);
        $subject = User::factory()->create(['data_visibility' => '2']);

        SupportTicket::create([
            'user_id' => $subject->id,
            'type' => 'contact',
            'subject' => 'Help',
            'status' => 'Open',
            'priority' => 'normal',
        ]);

        $payload = (new ProfileTransformer($viewer))->transform($subject->fresh());

        $this->assertArrayNotHasKey('support_tickets_count', $payload);
    }

    public function test_transform_includes_admin_profile_nav_counts_for_admin_viewing_other_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subject = User::factory()->create();
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $subject->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_to' => $subject->id,
            'user_id_from' => $other->id,
        ]);

        $payload = (new ProfileTransformer($admin))->transform($subject->fresh());

        $this->assertArrayHasKey('admin_trips_count', $payload);
        $this->assertArrayHasKey('admin_ratings_count', $payload);
        $this->assertSame(1, $payload['admin_trips_count']);
        $this->assertSame(1, $payload['admin_ratings_count']);
    }

    public function test_transform_does_not_expose_admin_profile_nav_counts_for_non_admin(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);
        $subject = User::factory()->create(['data_visibility' => '2']);

        $payload = (new ProfileTransformer($viewer))->transform($subject->fresh());

        $this->assertArrayNotHasKey('admin_trips_count', $payload);
        $this->assertArrayNotHasKey('admin_ratings_count', $payload);
    }

    public function test_transform_exposes_extended_admin_detail_fields_for_admin_viewing_other_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $subject = User::factory()->create();
        $subject->forceFill([
            'phone_verified' => true,
            'phone_verified_at' => '2025-05-10 08:00:00',
            'identity_validation_rejected_at' => '2025-05-11 09:00:00',
            'identity_validation_reject_reason' => 'Document unreadable',
            'validate_by_date' => '2025-06-30',
        ])->saveQuietly();

        $payload = (new ProfileTransformer($admin))->transform($subject->fresh());

        $this->assertSame(1, $payload['phone_verified']);
        $this->assertSame('2025-05-10 08:00:00', $payload['phone_verified_at']);
        $this->assertSame('2025-05-11 09:00:00', $payload['identity_validation_rejected_at']);
        $this->assertSame('Document unreadable', $payload['identity_validation_reject_reason']);
        $this->assertSame('2025-06-30', $payload['validate_by_date']);
    }

    public function test_transform_does_not_expose_extended_admin_detail_fields_for_non_admin(): void
    {
        $viewer = User::factory()->create(['is_admin' => false]);
        $subject = User::factory()->create(['data_visibility' => '2']);
        $subject->forceFill([
            'phone_verified' => true,
            'validate_by_date' => '2025-06-30',
        ])->saveQuietly();

        $payload = (new ProfileTransformer($viewer))->transform($subject->fresh());

        $this->assertArrayNotHasKey('phone_verified', $payload);
        $this->assertArrayNotHasKey('phone_verified_at', $payload);
        $this->assertArrayNotHasKey('identity_validation_rejected_at', $payload);
        $this->assertArrayNotHasKey('identity_validation_reject_reason', $payload);
        $this->assertArrayNotHasKey('validate_by_date', $payload);
    }
}
