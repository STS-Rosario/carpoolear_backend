<?php

namespace Tests\Unit\Repository;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use STS\Repository\FileRepository;
use Tests\TestCase;

/** Forces the GD branch so thumbnail math is covered even when Imagick is installed. */
final class FileRepositoryUsingGdThumbnails extends FileRepository
{
    protected function usesImagickForThumbnails(): bool
    {
        return false;
    }
}

class FileRepositoryTest extends TestCase
{
    private function testing_folder(): string
    {
        return 'testing-file-repo/';
    }

    private function testing_folder_path(): string
    {
        return (new FileRepository)->resolveUploadFolder($this->testing_folder());
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
        // Mutation intent: `date('mdYHis')` + microtime digits + extension (~54–56 str_replace / concat).
        $this->assertMatchesRegularExpression('/^\d{14}\d+\.txt$/', $newName);
    }

    public function test_create_from_file_creates_nested_folder_recursively(): void
    {
        // Mutation intent: preserve makeDirectory(..., 0777, true, true) for missing nested target folders.
        $tmp = sys_get_temp_dir().'/file-repo-'.uniqid('', true).'.txt';
        File::put($tmp, 'payload-nested');
        $folder = $this->testing_folder().'a/b/c/';
        $folderPath = (new FileRepository)->resolveUploadFolder($folder);
        if (File::isDirectory($folderPath)) {
            File::deleteDirectory($folderPath);
        }

        $newName = (new FileRepository)->createFromFile($tmp, $folder);

        $this->assertTrue(File::isDirectory($folderPath));
        $this->assertTrue(File::exists($folderPath.$newName));
    }

    public function test_create_from_data_writes_named_jpeg_using_gd_path(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }

        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean();
        imagedestroy($image);

        $repo = new FileRepositoryUsingGdThumbnails;
        $name = $repo->createFromData($binary, 'jpg', $this->testing_folder(), 'unit-thumb');

        $this->assertSame('unit-thumb.jpg', $name);
        $this->assertTrue(File::exists($this->testing_folder_path().$name));
        $this->assertGreaterThan(0, File::size($this->testing_folder_path().$name));

        $info = getimagesize($this->testing_folder_path().$name);
        $this->assertNotFalse($info);
        $this->assertSame(400, $info[0]);
        $this->assertSame(400, $info[1]);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
    }

    public function test_create_from_data_generates_filename_when_name_is_null(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }

        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean();
        imagedestroy($image);

        $name = (new FileRepositoryUsingGdThumbnails)->createFromData($binary, 'jpg', $this->testing_folder(), null);

        // Mutation intent: keep generated-name branch (microtime/date concatenation) and non-empty return.
        $this->assertStringEndsWith('.jpg', $name);
        $this->assertNotSame('.jpg', $name);
        $this->assertMatchesRegularExpression('/^\d{14}\d+\.jpg$/', $name);
        $this->assertTrue(File::exists($this->testing_folder_path().$name));
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

    public function test_delete_does_not_throw_when_file_missing(): void
    {
        // Mutation intent: `File::delete` on missing target (~85–88); guard stable behavior for double-delete / race paths.
        File::ensureDirectoryExists($this->testing_folder_path());
        $name = 'definitely-missing-'.uniqid('', true).'.txt';
        $full = $this->testing_folder_path().$name;

        $this->assertFileDoesNotExist($full);

        (new FileRepository)->delete($name, $this->testing_folder());

        $this->assertFileDoesNotExist($full);
    }

    public function test_resolve_upload_folder_in_testing_starts_under_sys_temp_namespace(): void
    {
        // Mutation intent: testing branch concatenates `sys_get_temp_dir()`, `carpoolear-test-uploads`, and normalized folder (~18–23 Concat*/UnwrapRtrim mutants).
        $repo = new FileRepository;
        $resolved = $repo->resolveUploadFolder($this->testing_folder());

        $expectedPrefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'carpoolear-test-uploads-'.getmypid().DIRECTORY_SEPARATOR;
        $this->assertStringStartsWith($expectedPrefix, $resolved);
    }

    public function test_resolve_upload_folder_does_not_insert_double_directory_separator_after_temp(): void
    {
        // Mutation intent: `UnwrapRtrim` on `sys_get_temp_dir()` yields `…/T//carpoolear…` when the OS temp path already ends with a separator.
        $resolved = (new FileRepository)->resolveUploadFolder($this->testing_folder());
        $normalized = str_replace('\\', '/', $resolved);

        $this->assertStringNotContainsString('//', $normalized);
    }

    public function test_resolve_upload_folder_trims_slashes_around_folder_argument_before_joining(): void
    {
        // Mutation intent: `trim($normalized, '/')` (~25 UnwrapTrim / concat mutants) strips outer slashes; inner `//` between segments is preserved from the input.
        $resolved = (new FileRepository)->resolveUploadFolder('///segment-one//segment-two///');
        $flat = str_replace('\\', '/', $resolved);

        $this->assertStringEndsWith('segment-one//segment-two/', $flat);
    }

    public function test_nomalize_trailing_slash_detection_uses_strict_slash_character(): void
    {
        // Mutation intent: `EqualToIdentical` on `$str[strlen($str) - 1] == '/'` (~39) — keep `/` byte as the only accepted “already normalized” sentinel.
        $repo = new FileRepository;
        $withSlash = public_path('nom-slash-test').'/';

        $this->assertSame($withSlash, $repo->nomalize($withSlash));
        $this->assertStringEndsWith('/', $repo->nomalize(rtrim($withSlash, '/')));
    }

    public function test_create_from_data_corrects_exif_orientation_before_thumbnail(): void
    {
        $fixture = base_path('tests/fixtures/orientation_6.jpg');
        if (! File::exists($fixture)) {
            $this->markTestSkipped('EXIF orientation fixture missing.');
        }

        $data = file_get_contents($fixture);
        $this->assertNotFalse($data);

        $repo = class_exists(\Imagick::class)
            ? new FileRepository
            : new FileRepositoryUsingGdThumbnails;

        $name = $repo->createFromData($data, 'jpg', $this->testing_folder(), 'exif-oriented');
        $path = $this->testing_folder_path().$name;
        $this->assertTrue(File::exists($path));

        $info = getimagesize($path);
        $this->assertNotFalse($info);
        // Fixture is portrait when oriented; thumbnail keeps aspect (max side 400).
        $this->assertGreaterThan($info[0], $info[1]);
    }

    public function test_create_from_data_imagick_writes_400_square_when_imagick_available(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick extension not available.');
        }

        $binary = $this->minimalValidPngBytes();
        $name = (new FileRepository)->createFromData($binary, 'png', $this->testing_folder(), 'imagick-square');

        $this->assertSame('imagick-square.png', $name);
        $path = $this->testing_folder_path().$name;
        $this->assertTrue(File::exists($path));

        $info = getimagesize($path);
        $this->assertNotFalse($info);
        $this->assertSame(400, $info[0]);
        $this->assertSame(400, $info[1]);
    }

    public function test_create_from_data_logs_error_when_imagick_cannot_read_blob(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick extension not available.');
        }

        Log::spy();

        $name = (new FileRepository)->createFromData('not-a-valid-image-binary-'.random_bytes(8), 'jpg', $this->testing_folder(), 'broken-input');

        $this->assertSame('broken-input.jpg', $name);
        Log::shouldHaveReceived('error')->once();

        $path = $this->testing_folder_path().$name;
        if (File::exists($path)) {
            $this->assertLessThanOrEqual(1, File::size($path));
        }
    }

    public function test_create_from_data_logs_error_when_gd_cannot_decode_payload(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available.');
        }

        Log::spy();

        $name = (new FileRepositoryUsingGdThumbnails)->createFromData('not-a-valid-image-binary', 'jpg', $this->testing_folder(), 'gd-bad');

        $this->assertSame('gd-bad.jpg', $name);
        Log::shouldHaveReceived('error')->once();
    }

    /** @return non-empty-string */
    private function minimalValidPngBytes(): string
    {
        // 1×1 transparent PNG (binary).
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
    }
}
