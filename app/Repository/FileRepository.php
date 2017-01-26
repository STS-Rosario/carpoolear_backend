<?php

namespace STS\Repository; 

use STS\Entities\Trip;
use STS\User;
use Validator;
use STS\Entities\SocialAccount;
use File;

class FileRepository
{
    protected $provider;
    public function __construct() {
 
    }
 
    public function create($filename, $folder = "image/") {
        $folder_path = $path = public_path($folder);
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

    public function delete($filename, $folder) {
        $folder_path = $path = public_path($folder);
        File::move( $folder_path . $filename);
    }

}