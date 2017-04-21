<?php

use Mockery as m;
use Tymon\JWTAuth\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApiAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected $userManager;
    protected $userLogic;

    public function __construct()
    {
    }

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
        $this->assertTrue($json->user != null);
    }

    public function testLogin()
    {
        $user = factory(STS\User::class)->create();

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
        $user = factory(STS\User::class)->create();
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

        $deviceLogic = $this->mock('STS\Contracts\Logic\Devices');
        //$deviceLogic->shouldReceive('updateBySession')->once()->andReturn(true);

        JWTAuth::shouldReceive('getToken')->once()->andReturn(new Token('a.b.c'));
        JWTAuth::shouldReceive('authenticate')->once()->andReturn($user);

        $response = $this->call('POST', 'api/retoken?token='.$json->token);
        $this->assertTrue($response->status() == 200);

        $json = $this->parseJson($response);
        $this->assertTrue($json->token != null);
        //$this->assertTrue($json->token != $token);

        m::close();
    }

    public function testUpdateProfile()
    {
        $user = factory(STS\User::class)->create();
        $this->actingAsApiUser($user);

        $data = [
            'name' => 'Mariano Botta',
        ];
        $response = $this->call('PUT', 'api/users', $data);

        $userUpdated = $this->parseJson($response);
        $this->assertTrue($response->status() == 200);
        $this->assertEquals($userUpdated->user->name, $data['name']);

        $u2 = STS\User::find($user->id);
        $this->assertEquals($userUpdated->user->name, $u2->name);
    }

    public function testShowProfile()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $this->actingAsApiUser($u1);

        $response = $this->call('GET', 'api/users/'.$u2->id);

        $this->assertTrue($response->status() == 200);
        $profile = $this->parseJson($response);
        $this->assertEquals($profile->data->name, $u2->name);
    }

    public function testActive()
    {
        $u1 = factory(STS\User::class)->create();
        $this->userLogic = $this->mock('STS\Contracts\Logic\User');
        $this->userLogic->shouldReceive('activeAccount')->once()->andReturn($u1);

        $response = $this->call('POST', 'api/activate/1234567890');

        $this->assertTrue($response->status() == 200);

        $response = $this->parseJson($response);
        $this->assertTrue($response->token != null);
        m::close();
    }

    public function testResetPassword()
    {
        $u1 = factory(STS\User::class)->create();
        $this->userLogic = $this->mock('STS\Contracts\Logic\User');
        $this->userLogic->shouldReceive('resetPassword')->once()->andReturn('asdqweasdqwe');

        $response = $this->call('POST', 'api/reset-password', ['email' => $u1->email]);

        $this->assertTrue($response->status() == 200);

        m::close();
    }

    public function testChagePassword()
    {
        $u1 = factory(STS\User::class)->create();
        $this->userLogic = $this->mock('STS\Contracts\Logic\User');
        $this->userLogic->shouldReceive('changePassword')->once()->andReturn(true);

        $response = $this->call('POST', 'api/change-password/1234567890');
        \Log::info($response->getContent());
        $this->assertTrue($response->status() == 200);

        m::close();
    }
}
