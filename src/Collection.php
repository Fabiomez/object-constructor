<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Collection
{
    public function __construct(
        public readonly string $itemType
    ) {
    }
}
