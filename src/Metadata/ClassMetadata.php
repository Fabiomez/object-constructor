<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Metadata;

final readonly class ClassMetadata
{
    /** @param list<ParameterMetadata> $parameters */
    public function __construct(
        public string $className,
        public array $parameters,
    ) {
    }
}
