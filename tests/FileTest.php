<?php

class FileTest extends TestCase { 

    protected $userManager;
    public function __construct() {

    } 

	public function testCreateFile()
	{
        $filesSystem = new \STS\Repository\FileRepository();
		$path = base_path("tests/imgTest.png");
        $name = $filesSystem->create($path);
        
        $true = File::exists(public_path("image/" . $name));
        $this->assertTrue($true);
	}
 
 
 

}
