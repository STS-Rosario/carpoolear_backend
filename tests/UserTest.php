<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use \STS\Contracts\Logic\User as UserLogic;

class UserTest extends TestCase { 

    use DatabaseTransactions;

    protected $userManager;
    public function __construct() {

    } 

	public function testCreateUser()
	{

        $userManager = \App::make('\STS\Contracts\Logic\User');
        $data = [
            "name" => "Mariano",
            "email" => "mariano@g2.com",
            "password" => "123456",
            "password_confirmation" => "123456"
        ];

        $u = $userManager->create($data);
        $this->assertTrue($u != null);
    }

    public function testCreateUserFail()
    {
        $userManager = \App::make('\STS\Contracts\Logic\User');
        $data = [
            "name" => "Mariano",
            "email" => "mariano@g.com",
            "password" => "123456"
        ];

        $u = $userManager->create($data);
        $this->assertNull($u);
    }

    public function testCreateUserRepited()
    {
        $userManager = \App::make('\STS\Contracts\Logic\User');
        $data = [
            "name" => "Mariano",
            "email" => "mariano@g1.com",
            "password" => "123456",
            "password_confirmation" => "123456"
        ];

        $u1 = $userManager->create($data);

        $u2 = $userManager->create($data);

        $this->assertNull($u2);
    }

    public function testUpdateUser()
    {
        $userManager = \App::make('\STS\Contracts\Logic\User');
        $data = [
            "name" => "Mariano",
            "email" => "mariano@g1.com",
            "password" => "123456",
            "password_confirmation" => "123456"
        ];

        $u1 = $userManager->create($data);

        $data = [
            "name" => "Pablo",
            "password" => "gatogato",
            "password_confirmation" => "gatogato",
            "patente" => "SOF 034"
        ];

        $u1 = $userManager->update($u1, $data);
        $this->assertTrue($u1 != null);
<<<<<<< HEAD

	}
=======
    }
>>>>>>> develop

    public function testActiveUser()
    {
        $token = str_random(40);
        $userManager = \App::make('\STS\Contracts\Logic\User');
        $u1 = factory(STS\User::class)->create([
            "activation_token" => $token,
            "active" => false
        ]);
         
        $user = $userManager->activeAccount($token);

        $this->assertTrue($user->id == $u1->id);
        $this->assertTrue($user->active);
    }

    public function testPasswordReset()
    {
        $this->expectsEvents(STS\Events\User\Reset::class);

        $userManager = \App::make('\STS\Contracts\Logic\User');
        $u1 = factory(STS\User::class)->create();
         
        $token = $userManager->resetPassword($u1->email);

        $c = \DB::table('password_resets')->where('email', $u1->email)->first();
        $this->assertTrue($c != null);

        $data = [
            'password' => 'asdasd',
            'password_confirmation' => 'asdasd'
        ];
        $resp = $userManager->changePassword($c->token, $data);
        $this->assertTrue($resp);

        $data = [
            "email" => $u1->email,
            "password" => "asdasd",
            "device_id" => 123456,
            "device_type" => "Android",
            "app_version" => 1
        ];
        $response = $this->call('POST', 'api/login', $data);
        $this->assertTrue($response->status() == 200);
    }
}
