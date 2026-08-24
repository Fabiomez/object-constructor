<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use BackedEnum;
use DateTimeInterface;
use Fabiomez\ObjectConstructor\Metadata\ClassMetadata;
use Fabiomez\ObjectConstructor\Metadata\MetadataCache;
use Fabiomez\ObjectConstructor\Metadata\ParameterMetadata;
use Fabiomez\ObjectConstructor\Options\ConstructionMode;
use Fabiomez\ObjectConstructor\Options\ConstructionOptions;
use Fabiomez\ObjectConstructor\Options\UnknownPropertyHandling;
use Fabiomez\ObjectConstructor\Resolver\ValueResolver;
use ReflectionAttribute;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;

final class Constructor
{
    /** @param list<ValueResolver> $resolvers */
    public function __construct(
        private readonly MetadataCache $metadata = new MetadataCache(),
        private readonly array $resolvers = [],
    ) {
    }

    /** @throws ReflectionException */
    public function create(string $className, mixed $inputData, ?ConstructionOptions $options = null): object
    {
        return $this->construct($className, $inputData, $options);
    }

    /** @throws ReflectionException */
    public function construct(string $className, mixed $inputData, ?ConstructionOptions $options = null): object
    {
        $options ??= new ConstructionOptions();
        $classMetadata = $this->metadata->get($className);

        if ($classMetadata->parameters === []) {
            if ($inputData !== [] && $inputData !== null) {
                throw new ConstructException('', "Class {$className} does not accept constructor arguments.");
            }
            return new $className();
        }

        if (!is_array($inputData) || count($classMetadata->parameters) === 1) {
            return $this->constructSingleValueObject($className, $classMetadata->parameters[0], $inputData, $options);
        }

        return $this->constructMultiValueObject($className, $classMetadata, $inputData, $options);
    }

    /** @throws ReflectionException */
    private function constructSingleValueObject(string $className, ParameterMetadata $parameter, mixed $inputData, ConstructionOptions $options): object
    {
        return new $className($this->constructParameterValue($parameter, $inputData, $options));
    }

    /** @throws ReflectionException */
    private function constructMultiValueObject(string $className, ClassMetadata $classMetadata, array $inputData, ConstructionOptions $options): object
    {
        if ($options->unknownProperties === UnknownPropertyHandling::FAIL) {
            $known = array_map(static fn (ParameterMetadata $parameter): string => $parameter->name, $classMetadata->parameters);
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

    /** @throws ReflectionException */
    private function constructParameterValue(ParameterMetadata $parameter, mixed $value, ConstructionOptions $options): mixed
    {
        foreach ($this->resolvers as $resolver) {
            if (!$resolver instanceof ValueResolver) {
                throw new \InvalidArgumentException('All resolvers must implement ValueResolver.');
            }
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
        return $this->constructIntersectionType($type, $value);
    }

    /** @throws ReflectionException */
    private function constructNamedType(ReflectionNamedType $type, mixed $value, ConstructionOptions $options): mixed
    {
        $name = $type->getName();
        if ($name === 'mixed') {
            return $value;
        }
        if ($type->isBuiltin()) {
            return $this->castBuiltin($name, $value, $options->mode);
        }
        if (is_a($name, BackedEnum::class, true)) {
            return $name::from($value);
        }
        if ($name === 'DateTime' || $name === 'DateTimeImmutable') {
            return $this->constructDateTime($name, $value);
        }
        if (is_object($value) && $value instanceof $name) {
            return $value;
        }

        $reflection = new \ReflectionClass($name);
        $factory = $reflection->getAttributes(Factoryable::class)[0] ?? null;
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

        return $this->construct($name, $value, $options);
    }

    private function castBuiltin(string $name, mixed $value, ConstructionMode $mode): mixed
    {
        if ($mode === ConstructionMode::STRICT) {
            $valid = match ($name) {
                'int' => is_int($value),
                'float' => is_float($value) || is_int($value),
                'string' => is_string($value),
                'bool' => is_bool($value),
                'array' => is_array($value),
                'object' => is_object($value),
                'iterable' => is_iterable($value),
                'callable' => is_callable($value),
                'null' => $value === null,
                default => true,
            };
            if (!$valid) {
                throw new ConstructException('', "Value is not compatible with {$name}.");
            }
            return $value;
        }

        return match ($name) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) || $value instanceof \Stringable ? (string) $value : $value,
            'bool' => is_string($value) ? filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value : (bool) $value,
            default => $value,
        };
    }

    /** @throws ReflectionException */
    private function constructUnionType(ReflectionUnionType $type, mixed $value, ConstructionOptions $options): mixed
    {
        foreach ($type->getTypes() as $candidate) {
            if (!$candidate instanceof ReflectionNamedType) {
                continue;
            }
            $name = $candidate->getName();
            if (!$candidate->isBuiltin() && is_object($value) && $value instanceof $name) {
                return $value;
            }
            if ($candidate->isBuiltin() && $this->isCompatibleBuiltin($name, $value)) {
                return $this->castBuiltin($name, $value, $options->mode);
            }
        }

        $errors = [];
        foreach ($type->getTypes() as $candidate) {
            if (!$candidate instanceof ReflectionNamedType || $candidate->getName() === 'null') {
                continue;
            }
            try {
                return $this->constructNamedType($candidate, $value, $options);
            } catch (Throwable $exception) {
                $errors[] = $candidate->getName() . ': ' . $exception->getMessage();
            }
        }
        throw new ConstructException('', 'Unable to resolve union type. ' . implode(' | ', $errors));
    }

    private function isCompatibleBuiltin(string $name, mixed $value): bool
    {
        return match ($name) {
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'iterable' => is_iterable($value),
            'callable' => is_callable($value),
            'mixed' => true,
            default => false,
        };
    }

    private function constructIntersectionType(ReflectionIntersectionType $type, mixed $value): mixed
    {
        if (!is_object($value)) {
            throw new ConstructException('', 'Intersection types require an object instance.');
        }
        foreach ($type->getTypes() as $candidate) {
            $name = $candidate->getName();
            if (!$value instanceof $name) {
                throw new ConstructException('', "Object does not satisfy {$name}.");
            }
        }
        return $value;
    }

    private function constructDateTime(string $className, mixed $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value instanceof $className ? $value : new $className($value->format(DateTimeInterface::ATOM));
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new ConstructException('', 'DateTime input must be a string, timestamp or DateTimeInterface.');
        }
        if (is_numeric($value)) {
            $date = new $className('@' . $value);
            $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            return $date;
        }
        return new $className($value);
    }

    /** @throws ReflectionException */
    private function constructCollectionItems(ReflectionAttribute $attribute, array $items, ConstructionOptions $options): array
    {
        $itemClassName = $attribute->newInstance()->itemType;
        $builtItems = [];
        foreach ($items as $key => $item) {
            $builtItems[$key] = $this->construct($itemClassName, $item, $options);
        }
        return $builtItems;
    }
}
