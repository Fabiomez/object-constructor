<?php

declare(strict_types=1);

namespace Tests;

use Fabiomez\ObjectConstructor\ConstructException;
use Fabiomez\ObjectConstructor\Constructor;
use Fabiomez\ObjectConstructor\Options\ConstructionMode;
use Fabiomez\ObjectConstructor\Options\ConstructionOptions;
use PHPUnit\Framework\TestCase;

final class CoverageBranchesTest extends TestCase
{
    public function testStrictNullableBuiltinAcceptsNull(): void
    {
        $object = (new Constructor())->construct(CoverageNullableObject::class, null, new ConstructionOptions(ConstructionMode::STRICT));
        self::assertNull($object->value);
    }

    public function testStrictMixedBuiltinReturnsOriginalValue(): void
    {
        $value = ['value' => 1];
        $object = (new Constructor())->construct(CoverageMixedObject::class, $value, new ConstructionOptions(ConstructionMode::STRICT));
        self::assertSame($value, $object->value);
    }

    public function testStrictCompatibleBuiltins(): void
    {
        $constructor = new Constructor();
        $options = new ConstructionOptions(ConstructionMode::STRICT);
        $callable = static fn (): string => 'ok';
        $object = new \stdClass();

        self::assertSame(1, $constructor->construct(CoverageIntObject::class, 1, $options)->value);
        self::assertSame(1.5, $constructor->construct(CoverageFloatObject::class, 1.5, $options)->value);
        self::assertSame('ok', $constructor->construct(CoverageStringObject::class, 'ok', $options)->value);
        self::assertTrue($constructor->construct(CoverageBoolObject::class, true, $options)->value);
        self::assertSame([], $constructor->construct(CoverageArrayObject::class, [], $options)->value);
        self::assertSame($object, $constructor->construct(CoverageObjectObject::class, $object, $options)->value);
        self::assertSame([], $constructor->construct(CoverageIterableObject::class, [], $options)->value);
        self::assertSame($callable, $constructor->construct(CoverageCallableObject::class, $callable, $options)->value);
    }

    public function testCoerciveBuiltinConversions(): void
    {
        $constructor = new Constructor();
        self::assertSame(12, $constructor->construct(CoverageIntObject::class, '12')->value);
        self::assertSame(12.5, $constructor->construct(CoverageFloatObject::class, '12.5')->value);
        self::assertSame('12', $constructor->construct(CoverageStringObject::class, 12)->value);
        self::assertSame('converted', $constructor->construct(CoverageStringObject::class, new CoverageStringable())->value);
        self::assertTrue($constructor->construct(CoverageBoolObject::class, 'true')->value);
        self::assertFalse($constructor->construct(CoverageBoolObject::class, 'false')->value);
    }

    public function testBooleanUnknownStringFallsThroughToNativeTypeError(): void
    {
        $this->expectException(\TypeError::class);
        (new Constructor())->construct(CoverageBoolObject::class, 'unknown');
    }

    /** @dataProvider builtinUnionProvider */
    public function testUnionBuiltinCompatibility(string $class, mixed $value): void
    {
        self::assertSame($value, (new Constructor())->construct($class, $value)->value);
    }

    /** @return iterable<string, array{class-string<object>, mixed}> */
    public static function builtinUnionProvider(): iterable
    {
        yield 'int' => [CoverageUnionIntObject::class, 1];
        yield 'float' => [CoverageUnionFloatObject::class, 1.5];
        yield 'string' => [CoverageUnionStringObject::class, 'ok'];
        yield 'bool' => [CoverageUnionBoolObject::class, true];
        yield 'array' => [CoverageUnionArrayObject::class, []];
        yield 'object' => [CoverageUnionObjectObject::class, new \stdClass()];
        yield 'iterable' => [CoverageUnionIterableObject::class, []];
        yield 'callable' => [CoverageUnionCallableObject::class, static fn (): string => 'ok'];
        yield 'false' => [CoverageUnionFalseObject::class, false];
    }

    public function testUnionAggregatesFailuresWhenNoCandidateCanBeConstructed(): void
    {
        $this->expectException(ConstructException::class);
        (new Constructor())->construct(CoverageFailingUnionObject::class, ['unexpected' => true]);
    }

    public function testDnfIntersectionCandidateIsHandledOnPhp82AndNewer(): void
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('DNF union types require PHP 8.2+.');
        }
        $className = 'Tests\\CoverageDnfObject';
        if (!class_exists($className, false)) {
            eval('namespace Tests; final class CoverageDnfObject { public function __construct(public readonly string|(CoverageUnionLeft&CoverageUnionRight) $value) {} }');
        }
        $value = new CoverageIntersectionValue();
        self::assertSame($value, (new Constructor())->construct($className, $value)->value);
    }

    public function testDnfInvalidValueReachesNativeTypeValidationOnPhp82AndNewer(): void
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('DNF union types require PHP 8.2+.');
        }
        $className = 'Tests\\CoverageFailingDnfObject';
        if (!class_exists($className, false)) {
            eval('namespace Tests; final class CoverageFailingDnfObject { public function __construct(public readonly string|(CoverageUnionLeft&CoverageUnionRight) $value) {} }');
        }
        $this->expectException(\TypeError::class);
        (new Constructor())->construct($className, new CoveragePartialIntersectionValue());
    }

    public function testIntersectionAcceptsObjectImplementingAllInterfaces(): void
    {
        $value = new CoverageIntersectionValue();
        self::assertSame($value, (new Constructor())->construct(CoverageIntersectionObject::class, $value)->value);
    }

    public function testIntersectionRejectsNonObject(): void
    {
        $this->expectException(ConstructException::class);
        (new Constructor())->construct(CoverageIntersectionObject::class, 'invalid');
    }

    public function testIntersectionRejectsObjectMissingInterface(): void
    {
        $this->expectException(ConstructException::class);
        (new Constructor())->construct(CoverageIntersectionObject::class, new CoveragePartialIntersectionValue());
    }
}

final class CoverageNullableObject { public function __construct(public readonly ?string $value) {} }
final class CoverageMixedObject { public function __construct(public readonly mixed $value) {} }
final class CoverageIntObject { public function __construct(public readonly int $value) {} }
final class CoverageFloatObject { public function __construct(public readonly float $value) {} }
final class CoverageStringObject { public function __construct(public readonly string $value) {} }
final class CoverageBoolObject { public function __construct(public readonly bool $value) {} }
final class CoverageArrayObject { public function __construct(public readonly array $value) {} }
final class CoverageObjectObject { public function __construct(public readonly object $value) {} }
final class CoverageIterableObject { public function __construct(public readonly iterable $value) {} }
final class CoverageCallableObject { public function __construct(callable $value) { $this->value = $value; } public readonly mixed $value; }
final class CoverageStringable implements \Stringable { public function __toString(): string { return 'converted'; } }
interface CoverageUnionLeft {}
interface CoverageUnionRight {}
final class CoverageUnionIntObject { public function __construct(public readonly int|CoverageUnionLeft $value) {} }
final class CoverageUnionFloatObject { public function __construct(public readonly float|CoverageUnionLeft $value) {} }
final class CoverageUnionStringObject { public function __construct(public readonly string|CoverageUnionLeft $value) {} }
final class CoverageUnionBoolObject { public function __construct(public readonly bool|CoverageUnionLeft $value) {} }
final class CoverageUnionArrayObject { public function __construct(public readonly array|CoverageUnionLeft $value) {} }
final class CoverageUnionObjectObject { public function __construct(public readonly object|int $value) {} }
final class CoverageUnionIterableObject { public function __construct(public readonly iterable|CoverageUnionLeft $value) {} }
final class CoverageUnionCallableObject { public function __construct(callable|CoverageUnionLeft $value) { $this->value = $value; } public readonly mixed $value; }
final class CoverageUnionFalseObject { public function __construct(public readonly false|CoverageUnionLeft $value) {} }
final class CoverageFailingUnionA { public function __construct(public readonly string $value, public readonly string $kind) {} }
final class CoverageFailingUnionB { public function __construct(public readonly string $value, public readonly string $kind) {} }
final class CoverageFailingUnionObject { public function __construct(public readonly CoverageFailingUnionA|CoverageFailingUnionB $value) {} }
final class CoverageIntersectionValue implements CoverageUnionLeft, CoverageUnionRight {}
final class CoveragePartialIntersectionValue implements CoverageUnionLeft {}
final class CoverageIntersectionObject { public function __construct(public readonly CoverageUnionLeft&CoverageUnionRight $value) {} }
