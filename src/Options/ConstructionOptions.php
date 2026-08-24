<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Options;

final class ConstructionOptions
{
    public function __construct(
        public readonly ConstructionMode $mode = ConstructionMode::COERCE,
        public readonly UnknownPropertyHandling $unknownProperties = UnknownPropertyHandling::IGNORE,
    ) {
    }
}
