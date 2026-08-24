<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Metadata;

final class ClassMetadata
{
    /** @param list<ParameterMetadata> $parameters */
    public function __construct(
        public readonly string $className,
        public readonly array $parameters,
    ) {
    }
}
