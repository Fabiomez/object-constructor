<?php

namespace Tests\Stubs;

class VehicleBus implements Vehicle
{
    public function __construct(
        public readonly string $name
    ) {
    }
}
