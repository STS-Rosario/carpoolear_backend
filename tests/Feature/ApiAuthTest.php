<?php

namespace Tests\Feature;

use Mockery as m;
use Tests\TestCase;
use STS\Models\User;
use Tymon\JWTAuth\Token;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApiAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected $userManager;

    protected $userLogic;

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testCreateUser()
    {
        $data = [
            'name'                  => 'Mariano',
            'email'                 => 'mariano@g1.com',
            'password'              => '123456',
            'password_confirmation' => '123456',
        ];
        $response = $this->call('POST', 'api/users', $data);

        $this->assertTrue($response->status() == 200);

        $json = $this->parseJson($response);
        $this->assertTrue($json->data != null);
    }

    /**
     * After registration, active users should receive a JWT so the client can log them in without a separate login call.
     */
    public function testRegistrationReturnsJwtTokenWhenUserIsActiveForAutoLogin()
    {
        $user = User::factory()->create([
            'active' => 1,
            'banned' => 0,
        ]);

        $this->mock(\STS\Services\Logic\UsersManager::class, function ($mock) use ($user) {
            $mock->shouldReceive('create')->once()->andReturn($user);
        });

        $data = [
            'name'                  => 'Auto Login Test',
            'email'                 => 'auto-login-test@example.com',
            'password'              => '123456',
            'password_confirmation' => '123456',
            'terms_and_conditions'  => '1',
            'token'                 => 'test-recaptcha-token',
        ];
        $response = $this->call('POST', 'api/users', $data);

        $this->assertEquals(200, $response->status());
        $json = $this->parseJson($response);
        $this->assertNotNull($json->data);
        $this->assertObjectHasProperty('token', $json);
        $this->assertNotEmpty($json->token);
        $this->assertIsString($json->token);
    }

    public function testLogin()
    {
        $user = \STS\Models\User::factory()->create();

        $data = [
            'email'       => $user->email,
            'password'    => '123456',
            'device_id'   => 123456,
            'device_type' => 'Android',
            'app_version' => 1,

        ];
        $response = $this->call('POST', 'api/login', $data);
        $this->assertTrue($response->status() == 200);

        $json = $this->parseJson($response);
        $this->assertTrue($json->token != null);
    }

    public function testRetoken()
    {
        $user = \STS\Models\User::factory()->create();
        $data = [
            'email'       => $user->email,
            'password'    => '123456',
            'device_id'   => 123456,
            'device_type' => 'Android',
            'app_version' => 1,
        ];
        $response = $this->call('POST', 'api/login', $data);
        $json = $this->parseJson($response);
        $token = $json->token;

        $deviceLogic = $this->mock(\STS\Services\Logic\DeviceManager::class);

        \JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        \JWTAuth::shouldReceive('setToken')->andReturnSelf();
        \JWTAuth::shouldReceive('checkOrFail')->andReturn(new \stdClass());
        \JWTAuth::shouldReceive('user')->andReturn($user);

        $response = $this->call('POST', 'api/retoken?token='.$json->token);
        $this->assertTrue($response->status() == 200);

        $json = $this->parseJson($response);
        $this->assertTrue($json->token != null);

        m::close();
    }

    public function testUpdateProfile()
    {
        $user = \STS\Models\User::factory()->create();
        $id = $user->id;
        $this->actingAs($user, 'api');

        $data = [
            'description' => 'Test description',
        ];
        $response = $this->call('PUT', 'api/users', $data);

        $userUpdated = $this->parseJson($response);
        $this->assertTrue($response->status() == 200);
        $this->assertEquals($userUpdated->data->description, $data['description']);

        $u2 = \STS\Models\User::find($id);
        $this->assertEquals($userUpdated->data->description, $u2->description);
    }

    /**
     * Forbidden/flagged properties are silently ignored (no error) for backward compatibility.
     * Backend filters them out, persists only allowed props, and sends Slack alert when value actually changes.
     */
    public function testUpdateProfileBlocksForbiddenProperties()
    {
        $user = \STS\Models\User::factory()->create(['name' => 'Original', 'banned' => 0]);
        $this->actingAs($user, 'api');

        // Sending is_admin=1: request succeeds (200), but is_admin is not persisted
        $response = $this->call('PUT', 'api/users', ['is_admin' => 1]);
        $this->assertTrue($response->status() == 200);
        $this->assertFalse(\STS\Models\User::find($user->id)->is_admin);

        // Sending banned=1: request succeeds, banned is not persisted (still 0)
        $response = $this->call('PUT', 'api/users', ['banned' => 1]);
        $this->assertTrue($response->status() == 200);
        $this->assertEquals(0, \STS\Models\User::find($user->id)->banned);

        // Sending name: request succeeds, name is not persisted (still Original)
        $response = $this->call('PUT', 'api/users', ['name' => 'Hacker']);
        $this->assertTrue($response->status() == 200);
        $this->assertEquals('Original', \STS\Models\User::find($user->id)->name);

        // Mixed: allowed + forbidden in same request - allowed is saved, forbidden ignored
        $response = $this->call('PUT', 'api/users', ['description' => 'New desc', 'name' => 'IgnoredName', 'email' => 'other@x.com']);
        $this->assertTrue($response->status() == 200);
        $reloaded = \STS\Models\User::find($user->id);
        $this->assertEquals('New desc', $reloaded->description);
        $this->assertEquals('Original', $reloaded->name);
        $this->assertEquals($user->email, $reloaded->email);
    }

    public function testShowProfile()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $response = $this->call('GET', 'api/users/'.$u2->id);

        $this->assertTrue($response->status() == 200);
        $profile = $this->parseJson($response);
        $this->assertEquals($profile->data->name, $u2->name);
    }

    public function testActive()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->userLogic = $this->mock(\STS\Services\Logic\UsersManager::class);
        $this->userLogic->shouldReceive('activeAccount')->once()->andReturn($u1);

        $response = $this->call('POST', 'api/activate/1234567890');

        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($response->token != null);
        m::close();
    }

    public function testResetPassword()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->userLogic = $this->mock(\STS\Services\Logic\UsersManager::class);
        $this->userLogic->shouldReceive('resetPassword')->once()->andReturn('asdqweasdqwe');

        $response = $this->call('POST', 'api/reset-password', ['email' => $u1->email]);

        $this->assertTrue($response->status() == 200);

        m::close();
    }

    public function testChagePassword()
    {
        $u1 = \STS\Models\User::factory()->create();
        $this->userLogic = $this->mock(\STS\Services\Logic\UsersManager::class);
        $this->userLogic->shouldReceive('changePassword')->once()->andReturn(true);

        $response = $this->call('POST', 'api/change-password/1234567890');
        //\Log::info($response->getContent());
        $this->assertTrue($response->status() == 200);

        m::close();
    }

    public function testIndex()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $u3 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->userLogic = $this->mock(\STS\Services\Logic\UsersManager::class);
        $this->userLogic->shouldReceive('index')->once()->andReturn(new Collection([$u2, $u3]));

        $response = $this->call('GET', 'api/users/list');
        $this->assertTrue($response->status() == 200);

        m::close();
    }
}
