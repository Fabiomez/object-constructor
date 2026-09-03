<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Metadata;

use ReflectionClass;
use ReflectionException;

final class MetadataCache
{
    /** @var array<string, ClassMetadata> */
    private array $cache = [];

    /** @throws ReflectionException */
    public function get(string $className): ClassMetadata
    {
        return $this->cache[$className] ??= $this->inspect($className);
    }

    /** @throws ReflectionException */
    private function inspect(string $className): ClassMetadata
    {
        /** @var class-string<object> $className */
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];
        $metadata = [];

        foreach ($parameters as $parameter) {
            $metadata[] = new ParameterMetadata(
                name: $parameter->getName(),
                type: $parameter->getType(),
                allowsNull: $parameter->allowsNull(),
                hasDefault: $parameter->isDefaultValueAvailable(),
                defaultValue: $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                reflection: $parameter,
            );
        }

        return new ClassMetadata($className, $metadata);
    }
}
