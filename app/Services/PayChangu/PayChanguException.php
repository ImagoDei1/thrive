<?php

declare(strict_types=1);

namespace App\Services\PayChangu;

use RuntimeException;

class PayChanguException extends RuntimeException
{
    /** @param  array<string, mixed>  $body */
    public function __construct(string $message, public readonly int $httpStatus = 0, public readonly array $body = [])
    {
        parent::__construct($message);
    }
}
