<?php

namespace STS\Services\Logic;

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
