<?php

namespace STS\Contracts\Repository; 
  
use File;

interface Files
{ 

    public function createFromFile($filename, $folder = "image/") ;

    public function createFromData($data, $extension, $folder = "image/") ;

    public function delete($filename, $folder = "image/") ;

}