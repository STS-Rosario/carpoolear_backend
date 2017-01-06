<?php

class UserTest extends TestCase {

    protected $userManager;
    public function __construct() {

        

    } 

	public function testCreateUser()
	{
        $userManager = new \STS\Services\Logic\UsersManager();
		$data = [
            "name" => "Mariano", 
            "email" => "mariano@g.com", 
            "password" => "123456", 
            "password_confirmation" => "123456"
        ];

        $u = $userManager->create($data);

        $this->assertTrue($u != null);
	}

    public function testCreateUserFail()
	{
        $userManager = new \STS\Services\Logic\UsersManager();
		$data = [
            "name" => "Mariano", 
            "email" => "mariano@g.com", 
            "password" => "123456"
        ];

        $u = $userManager->create($data);
        $this->assertNull($u);
    
	}

}
