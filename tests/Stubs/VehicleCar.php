<?php

namespace Tests\Stubs;

class VehicleCar implements Vehicle
{
    public function __construct(
        public readonly string $name
    ) {
    }
}