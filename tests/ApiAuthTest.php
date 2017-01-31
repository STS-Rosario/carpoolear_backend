<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Repository\DeviceRepository;

class ApiAuthTest extends TestCase { 
    use DatabaseTransactions;

    protected $userManager;
    public function __construct() {

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
        $response = $this->call('POST', 'api/registrar', $data);
        $this->assertTrue($response->status() == 200);

        $json = $this->parseJson($response);        
        $this->assertTrue($json->user != null);

        //$response = $this->call('POST', 'api/registrar', $data);
        //$this->assertResponseStatus(400);
        //$this->assertTrue($response->status() == 400);
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

        $devices = new DeviceRepository;
        $this->assertTrue( $devices->getUserDevices($json->user->id)->count() > 0 );

        $response = $this->call('POST', 'api/logoff?token=' . $json->token);
        $this->assertTrue($response->status() == 200);
        $this->assertTrue( $devices->getUserDevices($json->user->id)->count() == 0 );


	}

    public function testRetoken() 
    {
        $user = factory(STS\User::class)->create();
        //$token = \JWTAuth::fromUser($user);

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
        $this->assertTrue($json->token != $token);

    }
 
 
 

}
