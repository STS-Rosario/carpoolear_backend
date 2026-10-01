<?php

namespace STS\Services\ContributionCheck;

class ContributionCheckHttpException extends ContributionCheckFailedException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }

    /**
     * Rate limits, timeouts and server errors may succeed later; other client
     * errors (bad key, unknown model, invalid payload) will not.
     */
    public function isRetryable(): bool
    {
        return $this->status >= 500 || in_array($this->status, [408, 409, 425, 429], true);
    }
}
