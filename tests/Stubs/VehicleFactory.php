<?php

namespace Tests\Stubs;

class VehicleFactory
{
    public static function make(string $name): Vehicle
    {
        if ($name === 'car') {
            return new VehicleCar($name);
        }

        return new VehicleBus($name);
    }
}