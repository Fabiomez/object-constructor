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

    public function testResolvesUnionObject(): void
    {
        $value = new ChildObject('ok');
        $object = (new Constructor())->construct(UnionObject::class, ['value' => $value]);

        self::assertSame($value, $object->value);
    }

    public function testResolvesUnionObjectFromArray(): void
    {
        $object = (new Constructor())->construct(UnionObject::class, ['value' => ['value' => 'ok']]);

        self::assertInstanceOf(ChildObject::class, $object->value);
        self::assertSame('ok', $object->value->value);
    }

    public function testResolvesIntersectionType(): void
    {
        $value = new IntersectionValue();
        $object = (new Constructor())->construct(IntersectionObject::class, ['value' => $value]);

        self::assertSame($value, $object->value);
    }

    public function testRejectsInvalidIntersectionType(): void
    {
        $this->expectException(ConstructException::class);

        (new Constructor())->construct(IntersectionObject::class, ['value' => new PartialIntersectionValue()]);
    }

    public function testCollectionAttributeConstructsEachItem(): void
    {
        $object = (new Constructor())->construct(CollectionObject::class, [
            'items' => [
                'first' => ['value' => 'one'],
                10 => ['value' => 'ten'],
            ],
        ]);

        self::assertSame(['first', 10], array_keys($object->items));
        self::assertSame('one', $object->items['first']->value);
        self::assertSame('ten', $object->items[10]->value);
    }

    public function testCollectionRejectsNonArray(): void
    {
        $this->expectException(ConstructException::class);

        (new Constructor())->construct(CollectionObject::class, ['items' => 'invalid']);
    }

    public function testFactoryableAttributeCreatesValue(): void
    {
        $object = (new Constructor())->construct(FactoryObject::class, ['value' => 'ok']);

        self::assertSame('factory:ok', $object->value->value);
    }

    public function testFactoryableAttributeCanReturnExistingObject(): void
    {
        $product = new FactoryProduct('existing');
        $object = (new Constructor())->construct(FactoryObject::class, ['value' => $product]);

        self::assertSame($product, $object->value);
    }

    public function testCustomResolverCanHandleType(): void
    {
        $object = (new Constructor(resolvers: [new CustomStringResolver()]))->construct(
            CustomResolvedObject::class,
            ['value' => 'ok'],
        );

        self::assertSame('resolved:ok', $object->value->value);
    }

    public function testNonMatchingCustomResolverFallsBackToBuiltinHandling(): void
    {
        $object = (new Constructor(resolvers: [new CustomStringResolver()]))->construct(
            ScalarObject::class,
            '123',
        );

        self::assertSame(123, $object->value);
    }

    public function testCustomResolverReceivesConstructorAndParameter(): void
    {
        $resolver = new CapturingResolver();
        $object = (new Constructor(resolvers: [$resolver]))->construct(
            CustomResolvedObject::class,
            ['value' => 'ok'],
        );

        self::assertSame('resolved:ok', $object->value->value);
        self::assertInstanceOf(Constructor::class, $resolver->constructor);
        self::assertInstanceOf(ReflectionNamedType::class, $resolver->parameterType);
        self::assertSame(CustomValue::class, $resolver->parameterType->getName());
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
        $unionParameter = new ReflectionMethod(UnionObject::class, '__construct')->getParameters()[0];
        $intersectionParameter = new ReflectionMethod(IntersectionObject::class, '__construct')->getParameters()[0];
        $union = $unionParameter->getType();
        $intersection = $intersectionParameter->getType();

        self::assertInstanceOf(ReflectionUnionType::class, $union);
        self::assertInstanceOf(ReflectionIntersectionType::class, $intersection);
    }
}

final class SimpleObject
{
    public function __construct(public readonly string $value)
    {
    }
}

final class EmptyObject
{
}

final class ScalarObject
{
    public function __construct(public readonly int $value)
    {
    }
}

final class NullableScalarObject
{
    public function __construct(public readonly ?string $value)
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
        public readonly ?string $nullable = null,
    ) {
    }
}

final class RequiredObject
{
    public function __construct(public readonly string $value)
    {
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

final class EnumObject
{
    public function __construct(public readonly PersonType $value)
    {
    }
}

enum PersonType: string
{
    case PF = 'PF';
    case PJ = 'PJ';
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
    public function __construct(public readonly int|ChildObject|string $value)
    {
    }
}

final class IntersectionObject
{
    public function __construct(public readonly IntersectionLeft&IntersectionRight $value)
    {
    }
}

final class ChildObject
{
    public function __construct(public readonly string $value)
    {
    }
}

interface IntersectionLeft
{
}

interface IntersectionRight
{
}

final class IntersectionValue implements IntersectionLeft, IntersectionRight
{
}

final class PartialIntersectionValue implements IntersectionLeft
{
}

#[Collection(itemType: ChildObject::class)]
final class CollectionObject
{
    /** @param list<ChildObject> $items */
    public function __construct(public readonly array $items)
    {
    }
}

#[Factoryable(factory: FactoryObjectFactory::class)]
final class FactoryObject
{
    public function __construct(public readonly FactoryProduct $value)
    {
    }
}

final class FactoryProduct
{
    public function __construct(public readonly string $value)
    {
    }
}

final class FactoryObjectFactory
{
    public static function create(mixed $value): FactoryProduct
    {
        if ($value instanceof FactoryProduct) {
            return $value;
        }

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

final class CapturingResolver implements ValueResolver
{
    public ?Constructor $constructor = null;
    public ?ReflectionNamedType $parameterType = null;

    public function supports(ParameterMetadata $parameter, mixed $value): bool
    {
        return $parameter->type instanceof ReflectionNamedType
            && $parameter->type->getName() === CustomValue::class;
    }

    public function resolve(Constructor $constructor, ParameterMetadata $parameter, mixed $value): mixed
    {
        $this->constructor = $constructor;
        $this->parameterType = $parameter->type;

        return new CustomValue('resolved:' . $value);
    }
}

final class ParentObject
{
    public function __construct(public readonly ChildObject $child)
    {
    }
}
