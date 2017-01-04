<?php

namespace STS\Exceptions;

class ValidationException extends \Exception
{
    /**
     * @var int
     */
    protected $errors = null;

    /**
     * @param string  $message
     * @param int $statusCode
     */
    public function __construct($errors)
    {
        parent::__construct("Validation Error");

        if (! is_null($errors)) {
            $this->setErrors($errors);
        }
    }

    /**
     * @param int $statusCode
     */
    public function setErrors($errs)
    {
        $this->errors = $errs;
    }

    /**
     * @return int the status code
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
