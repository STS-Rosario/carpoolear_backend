<?php

namespace Tests\Unit\Transformers;

use STS\Models\User;
use STS\Transformers\ProfileTransformer;
use Tests\TestCase;

class ProfileTransformerTest extends TestCase
{
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

    public function test_transform_identity_validated_is_strict_boolean(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['identity_validated' => true])->saveQuietly();

        $payload = (new ProfileTransformer(null))->transform($user->fresh());

        $this->assertTrue($payload['identity_validated']);
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
}
