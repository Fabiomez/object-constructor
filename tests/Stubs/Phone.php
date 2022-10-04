<?php

declare(strict_types=1);

namespace Tests\Stubs;

class Phone
{
    public function __construct(
        public CountryCode $countryCode,
        public string $areaCode,
        public string $number,
    ) {
    }
}
