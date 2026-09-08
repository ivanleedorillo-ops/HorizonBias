<?php

namespace App\Exceptions;

use RuntimeException;

final class AiProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $category,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
