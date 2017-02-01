<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserTest extends TestCase { 
    use DatabaseTransactions;
 
 	public function testCreateUser()
	{
        $userManager = new \STS\Services\Logic\UsersManager();
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
        $userManager = new \STS\Services\Logic\UsersManager();
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
        $userManager = new \STS\Services\Logic\UsersManager();
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
        
        $userManager = new \STS\Services\Logic\UsersManager();

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
