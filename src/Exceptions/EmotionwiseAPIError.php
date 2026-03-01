<?php

declare(strict_types=1);

namespace Emotionwise\Exceptions;

class EmotionwiseAPIError extends EmotionwiseError
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly mixed $responseBody = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): mixed
    {
        return $this->responseBody;
    }
}
