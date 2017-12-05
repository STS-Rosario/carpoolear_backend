<?php

namespace STS\Repository;

use File;
use STS\Contracts\Repository\Files as FilesRepo;

class FileRepository implements FilesRepo
{
    public function __construct()
    {
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
        $folder_path = $this->nomalize(public_path($folder));
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
        $folder_path = $this->nomalize(public_path($folder));
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

        // Create Imagick object
        $im = new \Imagick();
        
        // Convert image into Imagick
        $im->readimageblob($data);

        $im->thumbnailImage(400, 400, true);

        $output = $im->getimageblob();

        File::put($folder_path.$newfilename, $output);

        return $newfilename;
    }

    public function delete($filename, $folder = 'image/')
    {
        $folder_path = $this->nomalize(public_path($folder));
        File::delete($folder_path.$filename);
    }
}
