<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Options;

final readonly class ConstructionOptions
{
    public function __construct(
        public ConstructionMode $mode = ConstructionMode::COERCE,
        public UnknownPropertyHandling $unknownProperties = UnknownPropertyHandling::IGNORE,
    ) {
    }
}
