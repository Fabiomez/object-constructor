<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Metadata;

use ReflectionException;
use ReflectionMethod;

final class MetadataCache
{
    /** @var array<class-string, ClassMetadata> */
    private array $cache = [];

    /** @throws ReflectionException */
    public function get(string $className): ClassMetadata
    {
        return $this->cache[$className] ??= $this->inspect($className);
    }

    /** @throws ReflectionException */
    private function inspect(string $className): ClassMetadata
    {
        $parameters = (new ReflectionMethod($className, '__construct'))->getParameters();
        $metadata = [];

        foreach ($parameters as $parameter) {
            $metadata[] = new ParameterMetadata(
                $parameter->getName(),
                $parameter->getType(),
                $parameter->allowsNull(),
                $parameter->isDefaultValueAvailable(),
                $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                $parameter,
            );
        }

        return new ClassMetadata($className, $metadata);
    }
}
