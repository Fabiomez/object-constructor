<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Metadata;

use ReflectionParameter;
use ReflectionType;

final class ParameterMetadata
{
    public function __construct(
        public readonly string $name,
        public readonly ?ReflectionType $type,
        public readonly bool $allowsNull,
        public readonly bool $hasDefault,
        public readonly mixed $defaultValue,
        public readonly ReflectionParameter $reflection,
    ) {
    }
}
