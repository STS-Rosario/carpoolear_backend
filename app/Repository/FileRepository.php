<?php

namespace STS\Repository;

use File;

class FileRepository
{
    public function __construct() {}

    /**
     * Writable uploads directory. In testing, avoid writing under `public/` (may be non-writable in CI/sandbox).
     */
    protected function uploadsFolder(string $folder): string
    {
        $normalized = $this->nomalize($folder);

        if (app()->environment('testing')) {
            // Use the system temp dir so CI / local runs are not blocked by permissions on
            // `storage/framework/testing` (often root-owned or non-writable in sandboxes).
            // Suffix with PID so a root-owned or permission-broken shared folder from another
            // process cannot block this PHP run (see FileRepositoryTest / FileTest).
            $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'carpoolear-test-uploads-'.getmypid();

            return $this->nomalize($base.DIRECTORY_SEPARATOR.trim($normalized, '/'));
        }

        return $this->nomalize(public_path($folder));
    }

    /** Exposed for tests that assert filesystem paths mirror repository behaviour. */
    public function resolveUploadFolder(string $folder): string
    {
        return $this->uploadsFolder($folder);
    }

    public function nomalize($str)
    {
        if ($str[strlen($str) - 1] == '/') {
            return $str;
        }

        return $str.'/';
    }

    public function createFromFile($filename, $folder = 'image/')
    {
        $folder_path = $this->uploadsFolder($folder);
        if (! File::isDirectory($folder_path)) {
            File::makeDirectory($folder_path, 0777, true, true);
        }

        $ext = File::extension($filename);
        $mil = str_replace('.', '', microtime());
        $mil = str_replace(' ', '', $mil);
        $newfilename = date('mdYHis').$mil.'.'.$ext;
        File::move($filename, $folder_path.$newfilename);

        return $newfilename;
    }

    public function createFromData($data, $extension, $folder = 'image/', $name = null)
    {
        $folder_path = $this->uploadsFolder($folder);
        if (! File::isDirectory($folder_path)) {
            File::makeDirectory($folder_path, 0777, true, true);
        }

        if ($name) {
            $newfilename = $name.'.'.$extension;
        } else {
            $mil = str_replace('.', '', microtime());
            $mil = str_replace(' ', '', $mil);
            $newfilename = date('mdYHis').$mil.'.'.$extension;
        }

        $imgPath = $folder_path.$newfilename;

        try {
            if (class_exists('Imagick')) {
                // Create Imagick object
                $im = new \Imagick;

                // Convert image into Imagick
                $im->readimageblob($data);

                $im->thumbnailImage(400, 400, true);

                $output = $im->getimageblob();

                File::put($imgPath, $output);
            } else {
                $im = imagecreatefromstring($data);

                $width = imagesx($im);
                $height = imagesy($im);
                $thumb = imagecreatetruecolor(400, 400);
                imagecopyresized($thumb, $im, 0, 0, 0, 0, 400, 400, $width, $height);

                imagejpeg($thumb, $imgPath, 100);
            }
        } catch (Exception $e) {
            \Log::error($e);
        }

        return $newfilename;
    }

    public function delete($filename, $folder = 'image/')
    {
        $folder_path = $this->uploadsFolder($folder);
        File::delete($folder_path.$filename);
    }
}
