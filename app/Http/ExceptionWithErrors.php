<?php

namespace STS\Http;

use Exception;
use Illuminate\Http\JsonResponse;

class ExceptionWithErrors extends Exception
{
    protected $errors;

    protected int $httpStatus;

    public function __construct($message, $errors = null, int $httpStatus = 422)
    {
        parent::__construct((string) $message);
        $this->errors = $errors;
        $this->httpStatus = $httpStatus;
    }

    public function report()
    {
        \Log::info($this->message);
    }

    public function render($request): JsonResponse
    {
        if (is_null($this->errors)) {
            return response()->json([
                'message' => $this->message,
            ], $this->httpStatus);
        } else {
            $errorsPayload = $this->errors;
            if (is_object($errorsPayload) && method_exists($errorsPayload, 'toArray')) {
                $errorsPayload = $errorsPayload->toArray();
            } elseif (is_string($errorsPayload)) {
                $errorsPayload = ['error' => [$errorsPayload]];
            } elseif (! is_array($errorsPayload)) {
                $errorsPayload = ['error' => [(string) $errorsPayload]];
            }

            return response()->json([
                'errors' => $errorsPayload,
                'message' => $this->message,
            ], $this->httpStatus);
        }
    }
}
