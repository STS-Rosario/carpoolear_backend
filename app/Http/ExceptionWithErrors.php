<?php
 
 namespace STS\Http;

 use Exception;
 use Illuminate\Http\JsonResponse;

 class ExceptionWithErrors extends Exception
 {
    protected $message;
    protected $errors;
    public function __construct($message, $errors = null) {
        $this->message = $message;
        $this->errors = $errors;
    }

     public function render($request): JsonResponse
     {
        if (is_null($this->errors)) {
            return response()->json([
                'message' => $this->message,
            ], 400);
        } else {
            return response()->json([
                'errors' => $this->errors->toArray(),
                'message' => $this->message,
            ], 400);
        }
     }
 }