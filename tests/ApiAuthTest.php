<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use \STS\Contracts\Repository\Devices as DeviceRepository;

class ApiAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected $userManager;
    public function __construct()
    {
    }

    protected function actingAsApiUser($user)
    {
        $this->app['api.auth']->setUser($user);

        return $this;
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testCreateUser()
    {
        $data = [
            "name" => "Mariano",
            "email" => "mariano@g1.com",
            "password" => "123456",
            "password_confirmation" => "123456"
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
            "email" => $user->email,
            "password" => "123456",
            "device_id" => 123456,
            "device_type" => "Android",
            "app_version" => 1
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
            "email" => $user->email,
            "password" => "123456",
            "device_id" => 123456,
            "device_type" => "Android",
            "app_version" => 1
        ];
        $response = $this->call('POST', 'api/login', $data);
        $json = $this->parseJson($response);
        $token = $json->token;

        $response = $this->call('POST', 'api/retoken?token=' . $json->token);
        $this->assertTrue($response->status() == 200);

        $json = $this->parseJson($response);
        $this->assertTrue($json->token != null);
        //$this->assertTrue($json->token != $token);
    }

    public function testUpdateProfile()
    {
        $user = factory(STS\User::class)->create();
        $this->actingAsApiUser($user);

        $data = [
            "name" => 'Mariano Botta'
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

        $response = $this->call('GET', 'api/users/' . $u2->id);
        
        $this->assertTrue($response->status() == 200);

        $profile = $this->parseJson($response);
        $this->assertEquals($profile->user->name, $u2->name);
    } 
}
