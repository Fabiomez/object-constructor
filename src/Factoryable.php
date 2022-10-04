<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use Attribute;
use Closure;

#[Attribute]
final class Factoryable
{
    private Closure $factory;

    public function __construct(
        callable $factory
    ) {
        $this->factory = Closure::fromCallable($factory);
    }

    public function create($inputData): mixed
    {
        $factory = $this->factory;
        return $factory($inputData);
    }
}
