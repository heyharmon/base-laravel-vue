<?php

namespace App\Exceptions;

class OpenAIException extends \Exception
{
    public function __construct(string $message, private string $errorCode, private int $statusCode)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isRetryable(): bool
    {
        $retryable = ['resource_unavailable', 'rate_limit', 'timeout', 'service_unavailable'];
        return in_array($this->errorCode, $retryable);
    }
}
