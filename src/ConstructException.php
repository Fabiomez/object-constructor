<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use RuntimeException;

class ConstructException extends RuntimeException
{
    public function __construct(
        private string $param,
        string $message
    ) {
        parent::__construct($message);
    }

    public function getParam(): string
    {
        return $this->param;
    }
}
