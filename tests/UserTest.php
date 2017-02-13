<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use \STS\Contracts\Logic\User as UserLogic;

class UserTest extends TestCase { 


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

	}

}
