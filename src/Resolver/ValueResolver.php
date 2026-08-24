<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Resolver;

use Fabiomez\ObjectConstructor\Constructor;
use Fabiomez\ObjectConstructor\Metadata\ParameterMetadata;

interface ValueResolver
{
    public function supports(ParameterMetadata $parameter, mixed $value): bool;

    public function resolve(Constructor $constructor, ParameterMetadata $parameter, mixed $value): mixed;
}
