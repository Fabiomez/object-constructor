<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Fabiomez\ObjectConstructor\Buildable;

#[Buildable([VehicleFactory::class, 'make'])]
interface Vehicle
{

}