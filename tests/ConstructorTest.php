<?php

declare(strict_types=1);

namespace Tests;

use Fabiomez\ObjectConstructor\ConstructException;
use Fabiomez\ObjectConstructor\Constructor;
use PHPUnit\Framework\TestCase;
use Tests\Stubs\Client;
use Tests\Stubs\CountryCode;
use Tests\Stubs\Name;
use Tests\Stubs\PersonType;
use Tests\Stubs\Phone;
use Tests\Stubs\Throphy;
use Tests\Stubs\Trophies;
use Tests\Stubs\VehicleBus;
use Tests\Stubs\VehicleCar;

class ConstructorTest extends TestCase
{
    public function testConstruction()
    {
        $inputData = [
            'id' => '1234',
            'name' => [
                'first' => 'Manuel',
                'last' => 'Silva'
            ],
            'personType' => 'PF',
            'phone' => [
                'countryCode' => '+55',
                'areaCode' => '11',
                'number' => '999999999'
            ],
            'scopes' => [
                'scope 1',
                'scope 2'
            ],
            'trophies' => [
                'golden',
                'silver',
                'bronze'
            ],
            'nonExistingParameter' => 'ignored value',
            'vehicleCar' => 'car',
            'vehicleBus' => 'bus',
        ];

        $constructor = new Constructor();

        $client = $constructor->construct(Client::class, $inputData);

        static::assertInstanceOf(Client::class, $client);

        static::assertInstanceOf(Name::class, $client->name);
        static::assertEquals('Manuel', $client->name->first);
        static::assertEquals('Silva', $client->name->last);

        static::assertInstanceOf(PersonType::class, $client->personType);
        static::assertEquals(PersonType::PF, $client->personType);

        static::assertInstanceOf(Phone::class, $client->phone);
        static::assertInstanceOf(CountryCode::class, $client->phone->countryCode);
        static::assertEquals('+55', $client->phone->countryCode->code);
        static::assertEquals('11', $client->phone->areaCode);
        static::assertEquals('999999999', $client->phone->number);

        static::assertEquals(['scope 1', 'scope 2'], $client->scopes);

        static::assertInstanceOf(Trophies::class, $client->trophies);
        static::assertInstanceOf(Throphy::class, $client->trophies->items[0]);
        static::assertEquals('golden', $client->trophies->items[0]->name);
        static::assertInstanceOf(Throphy::class, $client->trophies->items[1]);
        static::assertEquals('silver', $client->trophies->items[1]->name);
        static::assertInstanceOf(Throphy::class, $client->trophies->items[2]);
        static::assertEquals('bronze', $client->trophies->items[2]->name);

        static::assertInstanceOf(VehicleCar::class, $client->vehicleCar);
        static::assertEquals('car', $client->vehicleCar->name);
        static::assertInstanceOf(VehicleBus::class, $client->vehicleBus);
        static::assertEquals('bus', $client->vehicleBus->name);

        static::assertEquals(1234, $client->id);
        static::assertNull($client->optional);
        static::assertEquals('test', $client->default);
    }

    public function testAttributesErrorMapping()
    {
        $inputData = [
            'id' => 'Orange',
            'personType' => 'LL'
        ];

        try {
            $constructor = new Constructor();

            $constructor->construct(Client::class, $inputData);
        } catch (ConstructException $exception) {
            \var_dump($exception->getParam());
            \var_dump($exception->getMessage());
        }
    }
}
