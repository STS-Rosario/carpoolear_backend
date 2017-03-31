<?php

class FileTest extends TestCase
{
    protected $userManager;

    public function __construct()
    {
    }

    public function testCreateFile()
    {
        $filesSystem = \App::make('\STS\Contracts\Repository\Files');

        $path = base_path('tests/test_file.txt', 'image');
        File::put($path, 'HOLA');

        $name = $filesSystem->createFromFile($path);

        $true = File::exists(public_path('image/'.$name));
        $this->assertTrue($true);

        File::delete(public_path('image/'.$name));
    }
}
