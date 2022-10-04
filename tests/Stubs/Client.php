<?php

declare(strict_types=1);

namespace Tests\Stubs;

class Client
{
    public function __construct(
        public readonly int $id,
        public readonly PersonType $personType,
        public readonly Name $name,
        public readonly Phone $phone,
        public readonly array $scopes,
        public readonly Trophies $trophies,
        public readonly Vehicle $vehicleCar,
        public readonly Vehicle $vehicleBus,
        public ?string $optional,
        public string $default = 'test'
    ) {
    }
}
