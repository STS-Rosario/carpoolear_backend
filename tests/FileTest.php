<?php

namespace Tests;

use Tests\TestCase;

class FileTest extends TestCase
{
    protected $userManager;

    public function testCreateFile()
    {
        $filesSystem = \App::make(\STS\Repository\FileRepository::class);

        $path = base_path('tests/test_file.txt', 'image');
        \File::put($path, 'HOLA');

        $name = $filesSystem->createFromFile($path);

        $true = \File::exists(public_path('image/'.$name));
        $this->assertTrue($true);

        \File::delete(public_path('image/'.$name));
    }
}
