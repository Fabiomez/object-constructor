<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use RuntimeException;
use Throwable;

class ConstructException extends RuntimeException
{
    public function __construct(
        private readonly string $param,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getParam(): string
    {
        return $this->param;
    }
}
