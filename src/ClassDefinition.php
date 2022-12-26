<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor;

use ReflectionException;
use ReflectionMethod;

final class ClassDefinition
{
    protected array $parametersDefinition = [];
    protected ReflectionMethod $constructor;

    /**
     * @throws ReflectionException
     */
    public function __construct(
        public readonly string $className
    ) {
        $this->constructor = new ReflectionMethod($this->className, '__construct');
        $this->constructor->setAccessible(true);
        $this->initializeParameters();
    }

    private function initializeParameters(): void
    {
        foreach ($this->constructor->getParameters() as $parameter) {
            $this->parametersDefinition[] = new ParameterDefinition($parameter);
        }
    }
}
