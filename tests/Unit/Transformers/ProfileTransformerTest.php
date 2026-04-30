<?php

namespace Tests\Unit\Transformers;

use STS\Models\User;
use STS\Transformers\ProfileTransformer;
use Tests\TestCase;

class ProfileTransformerTest extends TestCase
{
    public function test_transform_includes_expected_public_profile_fields_and_last_connection_fallback(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
            'image' => 'profile.png',
            'last_connection' => null,
        ]);
        $user->forceFill([
            'description' => 'About me',
            'private_note' => 'Private',
            'positive_ratings' => 12,
            'negative_ratings' => 3,
            'birthday' => '1990-01-01',
            'gender' => 'f',
            'accounts' => null,
            'donations' => null,
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
        $this->assertSame(12, $payload['positive_ratings']);
        $this->assertSame(3, $payload['negative_ratings']);
        $this->assertSame('', $payload['last_connection']);
    }
}
