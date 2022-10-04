<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use BackedEnum;
use Exception;
use RuntimeException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use function count;
use function is_a;
use function is_array;
use function is_numeric;

class Constructor
{
    /**
     * @throws ReflectionException
     */
    public function construct(string $className, mixed $inputData): Object
    {
        $parameters = (new ReflectionMethod($className, '__construct'))->getParameters();

        if (!is_array($inputData) || count($parameters) === 1) {
            return $this->constructSingleValueObject($className, $parameters[0], $inputData);
        }

        return $this->constructMultiValueObject($className, $parameters, $inputData);
    }

    /**
     * @throws ReflectionException
     */
    private function constructSingleValueObject(string $className, ReflectionParameter $parameter, $inputData): mixed
    {
        return new $className($this->constructParameterValue($parameter, $inputData));
    }

    private function constructMultiValueObject(string $className, array $parameters, array $inputData)
    {
        $builtConstructorParams = [];
        /** @var ReflectionParameter $parameter */
        foreach ($parameters as $parameter) {
            try {
                $builtConstructorParams[$parameter->getName()] = isset($inputData[$parameter->getName()])
                    ? $this->constructParameterValue($parameter,$inputData[$parameter->getName()])
                    : $this->getParameterDefaultValueOrNull($parameter);
            } catch (ConstructException $exception) {
                throw new ConstructException(
                    "{$parameter->getName()} > {$exception->getParam()}",
                    $exception->getMessage()
                );
            } catch (Exception|\Error $exception) {
                throw new ConstructException(
                    $parameter->getName(),
                    $exception->getMessage()
                );
            }
        }

        return new $className(...$builtConstructorParams);;
    }

    private function getParameterDefaultValueOrNull(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new RuntimeException('Nom optional parameter not set.');
    }

    /**
     * @throws ReflectionException
     */
    private function constructParameterValue(ReflectionParameter $parameter, $value): mixed
    {
        $type = $parameter->getType();

        if (!$type) {
            return $value;
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->constructReflectionNamedTypeParameterValue($type, $value);
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->extractReflectionUnionTypeParamValue($type, $value);
        }

        return $this->extractReflectionIntersectionTypeParamValue($type, $value);
    }

    private function castValue(ReflectionNamedType $type, mixed $value): mixed
    {
        return match ($type->getName()) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            default => $value
        };
    }

    /**
     * @throws ReflectionException
     */
    private function constructReflectionNamedTypeParameterValue(ReflectionNamedType $type, mixed $value): mixed
    {
        if (is_a($type->getName(), BackedEnum::class, true)) {
            return $type->getName()::from($value);
        }

        if ($type->isBuiltin()) {
            return $this->castValue($type, $value);
        }

        $factoryableAttribute = (new ReflectionClass($type->getName()))
            ->getAttributes(Factoryable::class)[0] ?? false;
        if ($factoryableAttribute) {
            return $factoryableAttribute->newInstance()->create($value);
        }

        $collectionAttribute = (new ReflectionClass($type->getName()))
            ->getAttributes(Collection::class)[0] ?? false;
        if ($collectionAttribute) {
            $value = $this->constructCollectionItems($collectionAttribute, $value);
        }

        return $this->construct($type->getName(), $value);
    }

    private function extractReflectionUnionTypeParamValue(ReflectionUnionType $type, $value): mixed
    {
        throw new RuntimeException('No implementation to deal with ReflectionUnionType');
    }

    private function extractReflectionIntersectionTypeParamValue(ReflectionIntersectionType $type, mixed $value): mixed
    {
        throw new RuntimeException('No implementation to deal with ReflectionIntersectionType');
    }

    /**
     * @throws ReflectionException
     */
    private function constructCollectionItems(
        ReflectionAttribute $attribute,
        array $items
    ): array {
        $itemClassName = $attribute->newInstance()->itemType;

        $builtItems = [];
        foreach ($items as $item) {
            $builtItems[] = $this->construct($itemClassName, $item);
        }

        return $builtItems;
    }
}
