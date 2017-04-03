<?php

namespace STS\Services\Logic;
use Exception;

class BaseManager
{
    protected $errors;
    
    public function setErrors($errs)
    {
        $this->errors = $errs;
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
}