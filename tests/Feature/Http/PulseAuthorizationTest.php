<?php

namespace Tests\Feature\Http;

use STS\Models\User;
use Tests\TestCase;

class PulseAuthorizationTest extends TestCase
{
    public function test_non_admin_session_cannot_view_pulse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get('/pulse')
            ->assertForbidden();
    }

    public function test_admin_session_can_view_pulse(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();

        $this->actingAs($admin->fresh(), 'web')
            ->get('/pulse')
            ->assertOk();
    }
}
