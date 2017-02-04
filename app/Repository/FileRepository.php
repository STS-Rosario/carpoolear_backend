<?php

namespace STS\Repository; 
  
use STS\Contracts\Repository\Files as FilesRepo;  
use File;

class FileRepository implements FilesRepo
{ 
    public function __construct() {
 
    }

    public function nomalize($str) {
        if ($str[strlen($str) - 1] == "/") {
            return $str;
        }
        return $str . "/";
    }
 
    public function createFromFile($filename, $folder = "image/") {
        $folder_path =  $this->nomalize(public_path($folder));
        if (!File::isDirectory($folder_path)) {
            File::makeDirectory($folder_path, 0777, true, true);
        }
       
        $ext = File::extension($filename);
        $mil = str_replace(".", "", microtime());
        $mil = str_replace(" ", "", $mil);
        $newfilename = date('mdYHis') . $mil . "." . $ext;
        File::move($filename, $folder_path . $newfilename);

        return $newfilename;
    }

    public function createFromData($data, $extension, $folder = "image/") {
        $folder_path =  $this->nomalize(public_path($folder));
        if (!File::isDirectory($folder_path)) {
            File::makeDirectory($folder_path, 0777, true, true);
        }
        
        $mil = str_replace(".", "", microtime());
        $mil = str_replace(" ", "", $mil);
        $newfilename = date('mdYHis') . $mil . "." . $extension;
        File::put($folder_path . $newfilename, $data);

        return $newfilename;
    }

    public function delete($filename, $folder = "image/") {
        $folder_path = $this->nomalize(public_path($folder));
        File::move( $folder_path . $filename);
    }

}