<?php

namespace Tests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserTest extends TestCase
{
    use DatabaseTransactions;

    protected $userManager;

    public function testCreateUser()
    {
        \Illuminate\Support\Facades\Event::fake();
        $userManager = \App::make(\STS\Services\Logic\UsersManager::class);
        $data = [
            'name'                  => 'Mariano',
            'email'                 => 'marianoabotta@gmail.com',
            'password'              => '123456',
            'password_confirmation' => '123456',
        ];

        $u = $userManager->create($data);
        $this->assertTrue($u != null);
        \Illuminate\Support\Facades\Event::assertDispatched(\STS\Events\User\Create::class);
    }

    public function testCreateUserFail()
    {
        $userManager = \App::make(\STS\Services\Logic\UsersManager::class);
        $data = [
            'name'     => 'Mariano',
            'email'    => 'marianoabotta@gmail.com',
            'password' => '123456',
        ];

        $u = $userManager->create($data);
        $this->assertNull($u);
    }

    public function testCreateUserRepited()
    {
        \Illuminate\Support\Facades\Event::fake();
        $userManager = \App::make(\STS\Services\Logic\UsersManager::class);
        $data = [
            'name'                  => 'Mariano',
            'email'                 => 'mariano@g1.com',
            'password'              => '123456',
            'password_confirmation' => '123456',
        ];

        $u1 = $userManager->create($data);

        $u2 = $userManager->create($data);

        $this->assertNull($u2);
        \Illuminate\Support\Facades\Event::assertDispatched(\STS\Events\User\Create::class);
    }

    public function testUpdateUser()
    {
        $userManager = \App::make(\STS\Services\Logic\UsersManager::class);
        $data = [
            'name'                  => 'Mariano',
            'email'                 => 'mariano@g1.com',
            'password'              => '123456',
            'password_confirmation' => '123456',
        ];

        $u1 = $userManager->create($data);

        $data = [
            'name'                  => 'Pablo',
            'password'              => 'gatogato',
            'password_confirmation' => 'gatogato',
        ];

        $u1 = $userManager->update($u1, $data);
        $this->assertTrue($u1 != null);
    }

    public function testActiveUser()
    {
        $token = \Illuminate\Support\Str::random(40);
        $userManager = \App::make(\STS\Services\Logic\UsersManager::class);
        $u1 = \STS\Models\User::factory()->create([
            'activation_token' => $token,
            'active'           => false,
        ]);

        $user = $userManager->activeAccount($token);

        $this->assertTrue($user->id == $u1->id);
        $this->assertTrue($user->active);
    }

    public function testPasswordReset()
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['carpoolear.name_app' => 'TestApp', 'app.url' => 'http://localhost']);

        $userManager = \App::make(\STS\Services\Logic\UsersManager::class);
        $u1 = \STS\Models\User::factory()->create();

        $token = $userManager->resetPassword($u1->email);

        $c = \DB::table('password_resets')->where('email', $u1->email)->first();
        $this->assertTrue($c != null);

        $data = [
            'password'              => 'asdasd',
            'password_confirmation' => 'asdasd',
        ];
        $resp = $userManager->changePassword($c->token, $data);
        $this->assertTrue($resp);

        $data = [
            'email'       => $u1->email,
            'password'    => 'asdasd',
            'device_id'   => 123456,
            'device_type' => 'Android',
            'app_version' => 1,
        ];
        $response = $this->call('POST', 'api/login', $data);
        $this->assertTrue($response->status() == 200);
    }

    public function testIndex()
    {
        $userManager = \App::make(\STS\Services\Logic\UsersManager::class);
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $u3 = \STS\Models\User::factory()->create();

        $users = $userManager->index($u1, null);

        $this->assertTrue($users->count() == 2);
    }
}
