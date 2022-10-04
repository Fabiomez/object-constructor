<?php

declare(strict_types=1);

namespace Tests\Stubs;

class Name
{
    public function __construct(
        public string $first,
        public string $last
    ) {
    }

    public function getFullName(): string
    {
        return "$this->first $this->last";
    }
}
