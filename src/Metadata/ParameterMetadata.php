<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Metadata;

use ReflectionParameter;
use ReflectionType;

final readonly class ParameterMetadata
{
    public function __construct(
        public string $name,
        public ?ReflectionType $type,
        public bool $allowsNull,
        public bool $hasDefault,
        public mixed $defaultValue,
        public ReflectionParameter $reflection,
    ) {
    }
}
