<?php

namespace Tests\Feature\Http;

use Illuminate\Routing\Middleware\ThrottleRequests;
use STS\Models\User;
use Tests\TestCase;

class PulseAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_guest_cannot_access_pulse(): void
    {
        $response = $this->get('/pulse');

        $response->assertRedirect('/pulse/login');
    }

    public function test_admin_can_login_and_access_pulse(): void
    {
        $admin = $this->adminUser();

        $response = $this->post('/pulse/login', [
            'email' => $admin->email,
            'password' => '123456',
        ]);

        $response->assertRedirect('/pulse');
        $this->get('/pulse')->assertOk();
    }

    public function test_non_admin_cannot_login_to_pulse(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
        ]);

        $response = $this->from('/pulse/login')->post('/pulse/login', [
            'email' => $user->email,
            'password' => '123456',
        ]);

        $response->assertRedirect('/pulse/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_banned_admin_cannot_login(): void
    {
        $admin = $this->adminUser();
        $admin->forceFill(['banned' => true])->saveQuietly();

        $response = $this->from('/pulse/login')->post('/pulse/login', [
            'email' => $admin->email,
            'password' => '123456',
        ]);

        $response->assertRedirect('/pulse/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_logout_clears_session(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'web')
            ->post('/pulse/logout')
            ->assertRedirect(route('pulse.login'));

        $this->get('/pulse')->assertRedirect('/pulse/login');
    }
}
