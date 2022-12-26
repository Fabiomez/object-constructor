<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Fabiomez\ObjectConstructor\Factoryable;

#[Factoryable([VehicleFactory::class, 'make'])]
interface Vehicle
{
}
