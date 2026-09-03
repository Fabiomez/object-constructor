<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use Attribute;
use Closure;

#[Attribute(Attribute::TARGET_CLASS)]
final class Buildable
{
    private Closure $factory;

    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function build(mixed $inputData): mixed
    {
        $factory = $this->factory;
        return $factory($inputData);
    }
}
