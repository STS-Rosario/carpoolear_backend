<?php

namespace Tests\Unit\Services;

use STS\Models\IdentityVerificationEvent;
use STS\Models\User;
use STS\Services\UserIdentityVerificationResetService;
use Tests\TestCase;

class UserIdentityVerificationResetServiceTest extends TestCase
{
    public function test_clear_for_user_records_verification_reset_with_previous_state(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $this->actingAs($admin->fresh(), 'api');

        $user = User::factory()->create([
            'identity_validated' => true,
            'identity_validated_at' => now(),
            'identity_validation_type' => 'mercado_pago',
        ]);

        app(UserIdentityVerificationResetService::class)->clearForUser($user);

        $this->assertFalse((bool) $user->fresh()->identity_validated);
        $event = IdentityVerificationEvent::query()->where('name', 'verification_reset')->sole();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('admin', $event->method);
        $this->assertSame('users', $event->related_type);
        $this->assertSame($user->id, $event->related_id);
        $this->assertTrue($event->metadata['previous_validated'] ?? null);
        $this->assertSame('mercado_pago', $event->metadata['previous_validation_type'] ?? null);
        $this->assertSame($admin->id, $event->metadata['admin_id'] ?? null);
    }
}
