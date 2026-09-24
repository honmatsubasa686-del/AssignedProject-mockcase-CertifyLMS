<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class GeminiApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $upstreamStatus = null,
    ) {
        parent::__construct($message);
    }
}
