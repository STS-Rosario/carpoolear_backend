<?php

namespace STS\Services\Logic;

use STS\Entities\SocialAccount;
use STS\User;

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
