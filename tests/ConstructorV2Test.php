<?php

declare(strict_types=1);

namespace Tests;

use DateTime;
use DateTimeImmutable;
use Fabiomez\ObjectConstructor\Collection;
use Fabiomez\ObjectConstructor\ConstructException;
use Fabiomez\ObjectConstructor\Constructor;
use Fabiomez\ObjectConstructor\Factoryable;
use Fabiomez\ObjectConstructor\Metadata\ParameterMetadata;
use Fabiomez\ObjectConstructor\Options\ConstructionMode;
use Fabiomez\ObjectConstructor\Options\ConstructionOptions;
use Fabiomez\ObjectConstructor\Options\UnknownPropertyHandling;
use Fabiomez\ObjectConstructor\Resolver\ValueResolver;
use PHPUnit\Framework\TestCase;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

final class ConstructorV2Test extends TestCase
{
    public function testCreateAliasConstructsObject(): void
    {
        $object = (new Constructor())->create(SimpleObject::class, ['value' => 'ok']);

        self::assertInstanceOf(SimpleObject::class, $object);
        self::assertSame('ok', $object->value);
    }

    public function testConstructsClassWithoutConstructorFromEmptyArray(): void
    {
        $object = (new Constructor())->construct(EmptyObject::class, []);

        self::assertInstanceOf(EmptyObject::class, $object);
    }

    public function testConstructsClassWithoutConstructorFromNull(): void
    {
        $object = (new Constructor())->construct(EmptyObject::class, null);

        self::assertInstanceOf(EmptyObject::class, $object);
    }

    public function testRejectsInputForClassWithoutConstructor(): void
    {
        $this->expectException(ConstructException::class);

        (new Constructor())->construct(EmptyObject::class, ['unexpected' => true]);
    }

    public function testConstructsSingleScalarValueObjectWithCoercion(): void
    {
        $object = (new Constructor())->construct(ScalarObject::class, '123');

        self::assertSame(123, $object->value);
    }

    public function testConstructsSingleNullableValueObjectWithNull(): void
    {
        $object = (new Constructor())->construct(NullableScalarObject::class, null);

        self::assertNull($object->value);
    }

    public function testConstructsMultiPropertyObjectWithDefaults(): void
    {
        $object = (new Constructor())->construct(MultiPropertyObject::class, ['value' => 'ok']);

        self::assertSame('ok', $object->value);
        self::assertSame('default', $object->other);
    }

    public function testConstructsMultiPropertyObjectWithNullableMissingValue(): void
    {
        $object = (new Constructor())->construct(NullablePropertyObject::class, ['value' => 'ok']);

        self::assertNull($object->nullable);
    }

    public function testMissingRequiredParameterIsRejected(): void
    {
        $this->expectException(ConstructException::class);
        $this->expectExceptionMessage('Required constructor parameter is missing.');

        (new Constructor())->construct(RequiredObject::class, []);
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

    public function testUnknownPropertiesCanFail(): void
    {
        $this->expectException(ConstructException::class);
        $this->expectExceptionMessage('Unknown constructor property.');

        (new Constructor())->create(
            MultiPropertyObject::class,
            ['value' => 'ok', 'unexpected' => true],
            new ConstructionOptions(unknownProperties: UnknownPropertyHandling::FAIL),
        );
    }

    public function testStrictModeAcceptsCompatibleScalar(): void
    {
        $object = (new Constructor())->create(
            StrictObject::class,
            ['value' => 123],
            new ConstructionOptions(ConstructionMode::STRICT),
        );

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

    public function testStrictModeRejectsStringBoolean(): void
    {
        $this->expectException(ConstructException::class);

        (new Constructor())->create(
            StrictBooleanObject::class,
            ['value' => 'false'],
            new ConstructionOptions(ConstructionMode::STRICT),
        );
    }

    public function testCoerceModeParsesBooleanStrings(): void
    {
        $false = (new Constructor())->create(StrictBooleanObject::class, ['value' => 'false']);
        $true = (new Constructor())->create(StrictBooleanObject::class, ['value' => 'true']);

        self::assertFalse($false->value);
        self::assertTrue($true->value);
    }

    public function testSupportsBackedEnum(): void
    {
        $object = (new Constructor())->construct(EnumObject::class, ['value' => 'PF']);

        self::assertSame(PersonType::PF, $object->value);
    }

    public function testRejectsInvalidBackedEnum(): void
    {
        $this->expectException(\ValueError::class);

        (new Constructor())->construct(EnumObject::class, ['value' => 'INVALID']);
    }

    public function testSupportsDateTime(): void
    {
        $object = (new Constructor())->construct(DateTimeObject::class, [
            'value' => '2026-08-24T12:00:00+00:00',
        ]);

        self::assertInstanceOf(DateTime::class, $object->value);
        self::assertSame('2026-08-24T12:00:00+00:00', $object->value->format(DateTime::ATOM));
    }

    public function testSupportsDateTimeImmutable(): void
    {
        $object = (new Constructor())->construct(DateTimeImmutableObject::class, [
            'value' => '2026-08-24T12:00:00+00:00',
        ]);

        self::assertInstanceOf(DateTimeImmutable::class, $object->value);
    }

    public function testSupportsDateTimeFromTimestamp(): void
    {
        $object = (new Constructor())->construct(DateTimeImmutableObject::class, ['value' => 0]);

        self::assertSame('1970-01-01T00:00:00+00:00', $object->value->format(DateTime::ATOM));
    }

    public function testSupportsExistingDateTimeInterfaceValue(): void
    {
        $date = new DateTimeImmutable('2026-08-24T12:00:00+00:00');
        $object = (new Constructor())->construct(DateTimeObject::class, ['value' => $date]);

        self::assertInstanceOf(DateTime::class, $object->value);
        self::assertSame($date->format(DateTime::ATOM), $object->value->format(DateTime::ATOM));
    }

    public function testRejectsInvalidDateTimeInput(): void
    {
        $this->expectException(ConstructException::class);

        (new Constructor())->construct(DateTimeObject::class, ['value' => []]);
    }

    public function testResolvesUnionScalarWithoutConversion(): void
    {
        $object = (new Constructor())->construct(UnionObject::class, ['value' => 123]);

        self::assertSame(123, $object->value);
    }

    public function testResolvesUnionStringWithoutConversion(): void
    {
        $object = (new Constructor())->construct(UnionObject::class, ['value' => '123']);

        self::assertSame('123', $object->value);
    }

    public function testResolvesUnionObjectCandidateFromExistingObject(): void
    {
        $nested = new NestedObject('nested');
        $object = (new Constructor())->construct(ObjectUnionObject::class, ['value' => $nested]);

        self::assertSame($nested, $object->value);
    }

    public function testResolvesUnionObjectCandidateFromArray(): void
    {
        $object = (new Constructor())->construct(ObjectUnionObject::class, ['value' => ['value' => 'nested']]);

        self::assertInstanceOf(NestedObject::class, $object->value);
        self::assertSame('nested', $object->value->value);
    }

    public function testResolvesUnionContainingIntersectionType(): void
    {
        $value = new IntersectionValue();
        $object = (new Constructor())->construct(IntersectionUnionObject::class, ['value' => $value]);

        self::assertSame($value, $object->value);
    }

    public function testRejectsUnionContainingIntersectionWhenCandidateDoesNotMatch(): void
    {
        $this->expectException(ConstructException::class);

        (new Constructor())->construct(IntersectionUnionObject::class, ['value' => new OnlyLeft()]);
    }

    public function testRejectsIntersectionWithoutObject(): void
    {
        $this->expectException(ConstructException::class);

        (new Constructor())->construct(IntersectionObject::class, ['value' => 'invalid']);
    }

    public function testPreservesCollectionKeys(): void
    {
        $object = (new Constructor())->construct(CollectionObject::class, [
            'items' => [
                'first' => ['value' => 'one'],
                7 => ['value' => 'seven'],
            ],
        ]);

        self::assertArrayHasKey('first', $object->items);
        self::assertArrayHasKey(7, $object->items);
        self::assertSame('one', $object->items['first']->value);
        self::assertSame('seven', $object->items[7]->value);
    }

    public function testRejectsNonArrayCollectionInput(): void
    {
        $this->expectException(ConstructException::class);
        $this->expectExceptionMessage('Collection input must be an array.');

        (new Constructor())->construct(CollectionObject::class, ['items' => 'invalid']);
    }

    public function testConstructsEmptyCollection(): void
    {
        $object = (new Constructor())->construct(CollectionObject::class, ['items' => []]);

        self::assertSame([], $object->items);
    }

    public function testFactoryableCreatesTypedValue(): void
    {
        $object = (new Constructor())->construct(FactoryObject::class, 'raw');

        self::assertSame('factory:raw', $object->value->value);
    }

    public function testCustomResolverIsUsedBeforeBuiltInResolution(): void
    {
        $constructor = new Constructor(resolvers: [new CustomStringResolver()]);
        $object = $constructor->construct(CustomResolvedObject::class, ['value' => 'abc']);

        self::assertSame('resolved:abc', $object->value->value);
    }

    public function testResolverReceivesConstructorAndParameterMetadata(): void
    {
        $resolver = new CapturingResolver();
        $constructor = new Constructor(resolvers: [$resolver]);
        $object = $constructor->construct(CustomResolvedObject::class, ['value' => 'abc']);

        self::assertSame('resolved:abc', $object->value->value);
        self::assertSame($constructor, $resolver->constructor);
        self::assertInstanceOf(ReflectionNamedType::class, $resolver->parameterType);
    }

    public function testMetadataIsReusedForRepeatedConstruction(): void
    {
        $constructor = new Constructor();

        $first = $constructor->construct(SimpleObject::class, ['value' => 'one']);
        $second = $constructor->construct(SimpleObject::class, ['value' => 'two']);

        self::assertSame('one', $first->value);
        self::assertSame('two', $second->value);
    }

    public function testReflectsNamedUnionAndIntersectionTypes(): void
    {
        $union = new ReflectionMethod(UnionObject::class, '__construct')->getParameters()[0]->getType();
        $intersection = new ReflectionMethod(IntersectionObject::class, '__construct')->getParameters()[0]->getType();

        self::assertInstanceOf(ReflectionUnionType::class, $union);
        self::assertInstanceOf(ReflectionIntersectionType::class, $intersection);
    }

    public function testNestedConstructionFailureKeepsParameterPathAndPreviousException(): void
    {
        try {
            (new Constructor())->construct(ParentObject::class, [
                'child' => ['value' => []],
            ]);
            self::fail('Expected construction to fail.');
        } catch (ConstructException $exception) {
            self::assertSame('child > value', $exception->getParam());
            self::assertNotNull($exception->getPrevious());
        }
    }
}

final class EmptyObject
{
}

final class SimpleObject
{
    public function __construct(public readonly string $value)
    {
    }
}

final class ScalarObject
{
    public function __construct(public readonly int $value)
    {
    }
}

final class NullableScalarObject
{
    public function __construct(public readonly ?int $value)
    {
    }
}

final class RequiredObject
{
    public function __construct(public readonly string $value)
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

final class NullablePropertyObject
{
    public function __construct(
        public readonly string $value,
        public readonly ?string $nullable,
    ) {
    }
}

final class StrictObject
{
    public function __construct(public readonly int $value)
    {
    }
}

final class StrictBooleanObject
{
    public function __construct(public readonly bool $value)
    {
    }
}

enum PersonType: string
{
    case PF = 'PF';
}

final class EnumObject
{
    public function __construct(public readonly PersonType $value)
    {
    }
}

final class DateTimeObject
{
    public function __construct(public readonly DateTime $value)
    {
    }
}

final class DateTimeImmutableObject
{
    public function __construct(public readonly DateTimeImmutable $value)
    {
    }
}

final class UnionObject
{
    public function __construct(public readonly int|string $value)
    {
    }
}

final class NestedObject
{
    public function __construct(public readonly string $value)
    {
    }
}

final class ObjectUnionObject
{
    public function __construct(public readonly NestedObject|string $value)
    {
    }
}

interface LeftContract
{
}

interface RightContract
{
}

final class IntersectionValue implements LeftContract, RightContract
{
}

final class OnlyLeft implements LeftContract
{
}

final class IntersectionObject
{
    public function __construct(public readonly LeftContract&RightContract $value)
    {
    }
}

final class IntersectionUnionObject
{
    public function __construct(public readonly (LeftContract&RightContract)|string $value)
    {
    }
}

final class CollectionObject
{
    /** @param array<array-key, NestedObject> $items */
    public function __construct(
        #[Collection(NestedObject::class)]
        public readonly array $items,
    ) {
    }
}

final class FactoryProduct
{
    public function __construct(public readonly string $value)
    {
    }
}

final class FactoryObject
{
    public function __construct(
        #[Factoryable([FactoryObjectFactory::class, 'create'])]
        public readonly FactoryProduct $value,
    ) {
    }
}

final class FactoryObjectFactory
{
    public static function create(mixed $value): FactoryProduct
    {
        return new FactoryProduct('factory:' . (string) $value);
    }
}

final class CustomResolvedObject
{
    public function __construct(public readonly CustomValue $value)
    {
    }
}

final class CustomValue
{
    public function __construct(public readonly string $value)
    {
    }
}

final class CustomStringResolver implements ValueResolver
{
    public function supports(ParameterMetadata $parameter, mixed $value): bool
    {
        return $parameter->type instanceof ReflectionNamedType
            && $parameter->type->getName() === CustomValue::class;
    }

    public function resolve(Constructor $constructor, ParameterMetadata $parameter, mixed $value): mixed
    {
        return new CustomValue('resolved:' . $value);
    }
}

final class CapturingResolver extends CustomStringResolver
{
    public ?Constructor $constructor = null;
    public ?ReflectionNamedType $parameterType = null;

    public function resolve(Constructor $constructor, ParameterMetadata $parameter, mixed $value): mixed
    {
        $this->constructor = $constructor;
        $this->parameterType = $parameter->type;

        return parent::resolve($constructor, $parameter, $value);
    }
}

final class ParentObject
{
    public function __construct(public readonly ChildObject $child)
    {
    }
}

final class ChildObject
{
    public function __construct(public readonly int $value)
    {
    }
}
