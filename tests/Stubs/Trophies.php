<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Fabiomez\ObjectConstructor\Collection;

#[Collection(Throphy::class)]
class Trophies
{
    public function __construct(
        public array $items = []
    ) {
    }
}
