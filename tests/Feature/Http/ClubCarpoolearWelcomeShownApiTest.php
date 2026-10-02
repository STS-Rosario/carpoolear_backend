<?php

namespace Tests\Feature\Http;

use STS\Models\User;
use Tests\TestCase;

class ClubCarpoolearWelcomeShownApiTest extends TestCase
{
    public function test_mark_welcome_shown_requires_authentication(): void
    {
        $this->postJson('/api/club-carpoolear/welcome-shown')
            ->assertUnauthorized();
    }

    public function test_mark_welcome_shown_sets_flag_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['club_carpoolear_welcome_shown' => false])->save();

        $this->actingAs($user, 'api')
            ->postJson('/api/club-carpoolear/welcome-shown')
            ->assertOk()
            ->assertJson(['club_carpoolear_welcome_shown' => true]);

        $this->assertTrue($user->fresh()->club_carpoolear_welcome_shown);
    }
}
