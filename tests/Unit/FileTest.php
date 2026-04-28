<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use STS\Repository\FileRepository;
use Tests\TestCase;

class FileTest extends TestCase
{
    private FileRepository $files;

    private function testing_folder(): string
    {
        return 'testing-file-legacy/';
    }

    private function testing_folder_path(): string
    {
        return public_path($this->testing_folder());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->files = $this->app->make(FileRepository::class);
    }

    protected function tearDown(): void
    {
        $path = $this->testing_folder_path();
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
        parent::tearDown();
    }

    public function test_create_file_moves_temp_file_into_public_folder(): void
    {
        $tmp = sys_get_temp_dir().'/file-test-'.uniqid('', true).'.txt';
        File::put($tmp, 'HOLA');

        $name = $this->files->createFromFile($tmp, $this->testing_folder());

        $this->assertFileDoesNotExist($tmp);
        $target = $this->testing_folder_path().$name;
        $this->assertTrue(File::exists($target));
        $this->assertSame('HOLA', File::get($target));
        $this->assertStringEndsWith('.txt', $name);
    }

    public function test_delete_file_removes_created_file(): void
    {
        File::ensureDirectoryExists($this->testing_folder_path());
        $filename = 'delete-me.txt';
        File::put($this->testing_folder_path().$filename, 'bye');

        $this->files->delete($filename, $this->testing_folder());

        $this->assertFileDoesNotExist($this->testing_folder_path().$filename);
    }
}
