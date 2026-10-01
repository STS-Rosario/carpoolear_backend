<?php

namespace STS\Exceptions;

class MercadoPagoOAuthRequestException extends \Exception
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $responseBody = null,
    ) {
        parent::__construct($message);
    }
}
