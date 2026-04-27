<?php

namespace Tests\Unit\Repository;

use Illuminate\Support\Facades\File;
use STS\Repository\FileRepository;
use Tests\TestCase;

class FileRepositoryTest extends TestCase
{
    private function testing_folder(): string
    {
        return 'testing-file-repo/';
    }

    private function testing_folder_path(): string
    {
        return public_path($this->testing_folder());
    }

    protected function tearDown(): void
    {
        $path = $this->testing_folder_path();
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
        parent::tearDown();
    }

    public function test_nomalize_appends_slash_when_missing(): void
    {
        $repo = new FileRepository;
        $base = rtrim(public_path('foo'), '/');

        $this->assertSame($base.'/', $repo->nomalize($base));
    }

    public function test_nomalize_preserves_trailing_slash(): void
    {
        $repo = new FileRepository;
        $withSlash = public_path('bar').'/';

        $this->assertSame($withSlash, $repo->nomalize($withSlash));
    }

    public function test_create_from_file_moves_upload_into_public_folder(): void
    {
        $tmp = sys_get_temp_dir().'/file-repo-'.uniqid('', true).'.txt';
        File::put($tmp, 'payload');

        $repo = new FileRepository;
        $newName = $repo->createFromFile($tmp, $this->testing_folder());

        $this->assertFileDoesNotExist($tmp);
        $this->assertTrue(File::exists($this->testing_folder_path().$newName));
        $this->assertStringEndsWith('.txt', $newName);
    }

    public function test_create_from_data_writes_named_jpeg_using_gd_path(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }
        if (class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick is installed; repository uses Imagick branch instead of GD.');
        }

        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean();
        imagedestroy($image);

        $repo = new FileRepository;
        $name = $repo->createFromData($binary, 'jpg', $this->testing_folder(), 'unit-thumb');

        $this->assertSame('unit-thumb.jpg', $name);
        $this->assertTrue(File::exists($this->testing_folder_path().$name));
        $this->assertGreaterThan(0, File::size($this->testing_folder_path().$name));
    }

    public function test_delete_removes_file_under_folder(): void
    {
        $repo = new FileRepository;
        File::ensureDirectoryExists($this->testing_folder_path());
        $filename = 'to-delete.txt';
        File::put($this->testing_folder_path().$filename, 'bye');

        $repo->delete($filename, $this->testing_folder());

        $this->assertFileDoesNotExist($this->testing_folder_path().$filename);
    }
}
