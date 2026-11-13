<?php

namespace Modules\Servers\Voyar;

use RuntimeException;

/** Voyar answered with status "failed", an HTTP error, or could not be reached. */
class VoyarException extends RuntimeException
{
    /** @param array<string, mixed> $response */
    public function __construct(string $message, public readonly array $response = [], public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }
}
