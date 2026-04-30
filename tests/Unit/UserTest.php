<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use STS\Events\User\Create as CreateEvent;
use STS\Models\User;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class UserTest extends TestCase
{
    private UsersManager $userManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userManager = $this->app->make(UsersManager::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $email = 'marianoabotta@gmail.com'): array
    {
        return [
            'name' => 'Mariano',
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
        ];
    }

    public function test_create_user_dispatches_event_and_persists(): void
    {
        Event::fake([CreateEvent::class]);
        $u = $this->userManager->create($this->validPayload('user-'.uniqid('', true).'@example.com'));
        $this->assertNotNull($u);
        $this->assertFalse((bool) $u->active);
        Event::assertDispatched(CreateEvent::class);
    }

    public function test_create_user_fails_without_password_confirmation(): void
    {
        $data = $this->validPayload('fail-'.uniqid('', true).'@example.com');
        unset($data['password_confirmation']);
        $u = $this->userManager->create($data);
        $this->assertNull($u);
        $this->assertTrue($this->userManager->getErrors()->has('password'));
    }

    public function test_create_user_duplicate_email_is_rejected(): void
    {
        Event::fake([CreateEvent::class]);
        $data = $this->validPayload('mariano@g1.com');
        $u1 = $this->userManager->create($data);
        $this->assertNotNull($u1);
        $u2 = $this->userManager->create($data);

        $this->assertNull($u2);
        Event::assertDispatched(CreateEvent::class, 1);
    }

    public function test_update_user_changes_password_and_persists_hash(): void
    {
        $u1 = $this->userManager->create($this->validPayload('update-'.uniqid('', true).'@example.com'));
        $updated = $this->userManager->update($u1, [
            'password' => 'gatogato',
            'password_confirmation' => 'gatogato',
        ]);
        $this->assertNotNull($updated);
        $this->assertTrue(\Hash::check('gatogato', $updated->fresh()->password));
    }

    public function test_active_user_with_valid_token_activates_account(): void
    {
        $token = \Illuminate\Support\Str::random(40);
        $u1 = User::factory()->create([
            'activation_token' => $token,
            'active' => false,
        ]);

        $user = $this->userManager->activeAccount($token);

        $this->assertSame($u1->id, $user->id);
        $this->assertTrue((bool) $user->active);
        $this->assertNull($user->fresh()->activation_token);
    }

    public function test_password_reset_and_change_password_flow(): void
    {
        Queue::fake();
        config(['carpoolear.name_app' => 'TestApp', 'app.url' => 'http://localhost']);

        $u1 = User::factory()->create();

        $token = $this->userManager->resetPassword($u1->email);
        $this->assertNotNull($token);

        $c = \DB::table('password_resets')->where('email', $u1->email)->first();
        $this->assertNotNull($c);

        $resp = $this->userManager->changePassword($c->token, [
            'password' => 'asdasd',
            'password_confirmation' => 'asdasd',
        ]);
        $this->assertTrue($resp);
        $this->assertTrue(\Hash::check('asdasd', $u1->fresh()->password));
    }

    public function test_index_excludes_requesting_user(): void
    {
        $u1 = User::factory()->create();
        User::factory()->create();
        User::factory()->create();

        $users = $this->userManager->index($u1, null);
        $this->assertCount(2, $users);
        $this->assertFalse($users->pluck('id')->contains($u1->id));
    }
}
