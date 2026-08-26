<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use Fabiomez\ObjectConstructor\ConstructException;
use Fabiomez\ObjectConstructor\Constructor;
use Fabiomez\ObjectConstructor\Options\ConstructionMode;
use Fabiomez\ObjectConstructor\Options\ConstructionOptions;
use Fabiomez\ObjectConstructor\Options\UnknownPropertyHandling;
use PHPUnit\Framework\TestCase;

final class ConstructorV2Test extends TestCase
{
    public function testConstructsClassWithoutConstructor(): void
    {
        $object = (new Constructor())->create(EmptyObject::class, []);
        self::assertInstanceOf(EmptyObject::class, $object);
    }

    public function testSupportsDateTimeAndUnionTypes(): void
    {
        $object = (new Constructor())->create(ModernObject::class, [
            'createdAt' => '2026-08-24T12:00:00+00:00',
            'value' => 123,
        ]);

        self::assertInstanceOf(DateTimeImmutable::class, $object->createdAt);
        self::assertSame(123, $object->value);
    }

    public function testStrictModeRejectsStringInteger(): void
    {
        $this->expectException(ConstructException::class);
        (new Constructor())->create(
            StrictObject::class,
            ['value' => '123'],
            new ConstructionOptions(ConstructionMode::STRICT),
        );
    }

    public function testUnknownPropertiesCanFail(): void
    {
        $this->expectException(ConstructException::class);
        (new Constructor())->create(
            MultiPropertyObject::class,
            ['value' => 'ok', 'unexpected' => true],
            new ConstructionOptions(
                unknownProperties: UnknownPropertyHandling::FAIL,
            ),
        );
    }

    public function testUnknownPropertiesAreIgnoredByDefault(): void
    {
        $object = (new Constructor())->create(MultiPropertyObject::class, [
            'value' => 'ok',
            'unexpected' => true,
        ]);
        self::assertSame('ok', $object->value);
        self::assertSame('default', $object->other);
    }
}

final class EmptyObject
{
}

final class ModernObject
{
    public function __construct(
        public readonly DateTimeImmutable $createdAt,
        public readonly int|string $value,
    ) {
    }
}

final class StrictObject
{
    public function __construct(public readonly int $value)
    {
    }
}

final class MultiPropertyObject
{
    public function __construct(
        public readonly string $value,
        public readonly string $other = 'default',
    ) {
    }
}
