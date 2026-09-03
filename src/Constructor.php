<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use BackedEnum;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Fabiomez\ObjectConstructor\Builders\BuiltInBuilder;
use Fabiomez\ObjectConstructor\Builders\EnumBuilder;
use Fabiomez\ObjectConstructor\Metadata\ClassMetadata;
use Fabiomez\ObjectConstructor\Metadata\MetadataCache;
use Fabiomez\ObjectConstructor\Metadata\ParameterMetadata;
use Fabiomez\ObjectConstructor\Options\ConstructionMode;
use Fabiomez\ObjectConstructor\Options\ConstructionOptions;
use Fabiomez\ObjectConstructor\Options\UnknownPropertyHandling;
use Fabiomez\ObjectConstructor\Resolver\ValueResolver;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Stringable;
use Throwable;
use UnitEnum;

final class Constructor
{
    /** @param list<ValueResolver> $resolvers */
    public function __construct(
        private readonly MetadataCache $metadata = new MetadataCache(),
        private readonly array $resolvers = [],
    ) {
    }


    public function create(string $className, mixed $inputData, ?ConstructionOptions $options = null): object
    {
        return $this->construct($className, $inputData, $options);
    }

    public function construct(string $className, mixed $inputData, ?ConstructionOptions $options = null): object
    {
        $options ??= new ConstructionOptions();
        $classMetadata = $this->metadata->get($className);

        if ($classMetadata->parameters === []) {
            if ($inputData !== [] && $inputData !== null) {
                throw new ConstructException('', "Class $className does not accept constructor arguments.");
            }
            return new $className();
        }

        if (!is_array($inputData) || count($classMetadata->parameters) === 1) {
            return $this->constructSingleAttributeObject(
                $className,
                $classMetadata->parameters[0],
                $inputData,
                $options
            );
        }

        return $this->constructMultiAttributeObject($className, $classMetadata, $inputData, $options);
    }

    /**
     * @throws ReflectionException|\DateInvalidTimeZoneException
     */
    private function constructSingleAttributeObject(
        string $className,
        ParameterMetadata $parameter,
        mixed $inputData,
        ConstructionOptions $options,
    ): object {
        return new $className($this->constructParameterValue($parameter, $inputData, $options));
    }

    /**
     * @param array<array-key, mixed> $inputData
     */
    private function constructMultiAttributeObject(
        string $className,
        ClassMetadata $classMetadata,
        array $inputData,
        ConstructionOptions $options,
    ): object {
        if ($options->unknownProperties === UnknownPropertyHandling::FAIL) {
            $known = array_map(
                static fn (ParameterMetadata $parameter): string => $parameter->name,
                $classMetadata->parameters
            );
            $unknown = array_diff(array_keys($inputData), $known);
            if ($unknown !== []) {
                throw new ConstructException((string) reset($unknown), 'Unknown constructor property.');
            }
        }

        $arguments = [];
        foreach ($classMetadata->parameters as $parameter) {
            try {
                $arguments[$parameter->name] = array_key_exists($parameter->name, $inputData)
                    ? $this->constructParameterValue($parameter, $inputData[$parameter->name], $options)
                    : $this->getMissingParameterValue($parameter);
            } catch (ConstructException $exception) {
                throw new ConstructException(
                    $parameter->name . ($exception->getParam() !== '' ? " > {$exception->getParam()}" : ''),
                    $exception->getMessage(),
                    $exception,
                );
            } catch (Throwable $exception) {
                throw new ConstructException($parameter->name, $exception->getMessage(), $exception);
            }
        }

        return new $className(...$arguments);
    }

    private function getMissingParameterValue(ParameterMetadata $parameter): mixed
    {
        if ($parameter->hasDefault) {
            return $parameter->defaultValue;
        }
        if ($parameter->allowsNull) {
            return null;
        }
        throw new ConstructException($parameter->name, 'Required constructor parameter is missing.');
    }

    /**
     * @throws ReflectionException|\DateInvalidTimeZoneException
     */
    private function constructParameterValue(ParameterMetadata $parameter, mixed $value, ConstructionOptions $options): mixed
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($parameter, $value)) {
                return $resolver->resolve($this, $parameter, $value);
            }
        }

        if ($value === null && $parameter->allowsNull) {
            return null;
        }

        $type = $parameter->type;
        if ($type === null) {
            return $value;
        }
        if ($type instanceof ReflectionNamedType) {
            return $this->constructNamedType($type, $value, $options);
        }
        if ($type instanceof ReflectionUnionType) {
            return $this->constructUnionType($type, $value, $options);
        }
        if ($type instanceof ReflectionIntersectionType) {
            return $this->constructIntersectionType($type, $value);
        }

        throw new ConstructException('', 'Unsupported reflection type.');
    }

    /**
     * @throws ReflectionException|\DateInvalidTimeZoneException
     */
    private function constructNamedType(ReflectionNamedType $type, mixed $value, ConstructionOptions $options): mixed
    {
        $typeName = $type->getName();
        if ($typeName === 'mixed') {
            return $value;
        }
        if ($type->isBuiltin()) {
            return BuiltInBuilder::buildByMode($typeName, $value, $options->mode);
        }
        if (is_a($typeName, UnitEnum::class, true)) {
            return EnumBuilder::build($typeName, $value);
        }
        if ($typeName === DateTime::class) {
            return $this->constructDateTime($value, false);
        }
        if ($typeName === DateTimeImmutable::class) {
            return $this->constructDateTime($value, true);
        }
        if ($value instanceof $typeName) {
            return $value;
        }

        /** @var class-string<object> $typeName */
        $reflection = new ReflectionClass($typeName);
        $factory = $reflection->getAttributes(Buildable::class)[0] ?? null;
        if ($factory !== null) {
            return $factory->newInstance()->create($value);
        }

        $collection = $reflection->getAttributes(Collection::class)[0] ?? null;
        if ($collection !== null) {
            if (!is_array($value)) {
                throw new ConstructException('', 'Collection input must be an array.');
            }
            $value = $this->constructCollectionItems($collection, $value, $options);
        }

        return $this->construct($typeName, $value, $options);
    }

    private function constructUnionType(
        ReflectionUnionType $type,
        mixed $value,
        ConstructionOptions $options
    ): mixed {
        $candidates = $type->getTypes();

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof ReflectionNamedType) {
                continue;
            }
            $name = $candidate->getName();
            if (!$candidate->isBuiltin() && $value instanceof $name) {
                return $value;
            }
            if ($candidate->isBuiltin() && BuiltInBuilder::isCompatible($name, $value)) {
                return BuiltInBuilder::buildByMode($name, $value, $options->mode);
            }
        }

        $errors = [];
        foreach ($candidates as $candidate) {
            $candidateName = $this->describeReflectionType($candidate);
            try {
                return $this->constructUnionCandidate($candidate, $value, $options);
            } catch (Throwable $exception) {
                $errors[] = $candidateName . ': ' . $exception->getMessage();
            }
        }

        throw new ConstructException('', 'Unable to resolve union type. ' . implode(' | ', $errors));
    }

    /**
     * @throws ReflectionException|\DateInvalidTimeZoneException
     */
    private function constructUnionCandidate(ReflectionType $candidate, mixed $value, ConstructionOptions $options): mixed
    {
        if ($candidate instanceof ReflectionNamedType) {
            return $this->constructNamedType($candidate, $value, $options);
        }

        if ($candidate instanceof ReflectionIntersectionType) {
            return $this->constructIntersectionType($candidate, $value);
        }

        throw new ConstructException('', 'Unsupported union member type.');
    }

    private function constructIntersectionType(ReflectionIntersectionType $type, mixed $value): object
    {
        if (!is_object($value)) {
            throw new ConstructException('', 'Intersection types require an object instance.');
        }
        foreach ($type->getTypes() as $candidate) {
            if (!$candidate instanceof ReflectionNamedType) {
                throw new ConstructException('', 'Unsupported intersection member type.');
            }
            $name = $candidate->getName();
            if (!$value instanceof $name) {
                throw new ConstructException('', "Object does not satisfy $name.");
            }
        }
        return $value;
    }

    private function describeReflectionType(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }
        return (string) $type;
    }

    /**
     * @throws \DateInvalidTimeZoneException|Exception
     */
    private function constructDateTime(mixed $value, bool $immutable): DateTimeInterface
    {
        $className = $immutable ? DateTimeImmutable::class : DateTime::class;
        if ($value instanceof DateTimeInterface) {
            return $value instanceof $className ? $value : new $className($value->format(DateTimeInterface::ATOM));
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new ConstructException('', 'DateTime input must be a string, timestamp or DateTimeInterface.');
        }
        if (is_numeric($value)) {
            if ($immutable) {
                $date = new DateTimeImmutable('@' . $value);
                return $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            }
            $date = new DateTime('@' . $value);
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $date;
        }
        return new $className($value);
    }

    /**
     * @param ReflectionAttribute<Collection> $attribute
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     * @throws ReflectionException|\DateInvalidTimeZoneException
     */
    private function constructCollectionItems(ReflectionAttribute $attribute, array $items, ConstructionOptions $options): array
    {
        /** @var class-string<object> $itemClassName */
        $itemClassName = $attribute->newInstance()->itemType;
        return array_map(function ($item) use ($itemClassName, $options) {
            return $this->construct($itemClassName, $item, $options);
        }, $items);
    }
}
