<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Builders;

use Fabiomez\ObjectConstructor\ConstructException;
use Fabiomez\ObjectConstructor\Options\ConstructionMode;
use Stringable;

abstract class BuiltInBuilder
{
    final static public function isCompatible(string $name, mixed $value): bool
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

    final public static function build(string $name, mixed $value): mixed
    {
        return match ($name) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'string' => is_scalar($value) || $value instanceof Stringable ? (string) $value : $value,
            'bool' => is_string($value) ?
                filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value :
                (bool) $value,
            default => $value,
        };
    }

    final public static function buildStrict(string $name, mixed $value): mixed
    {
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
            throw new ConstructException('', "Value is not compatible with $name.");
        }
        return $value;
    }

    final public static function buildByMode(string $name, mixed $value, ConstructionMode $mode): mixed
    {
        if ($mode === ConstructionMode::COERCE) {
            return BuiltInBuilder::build($name, $value);
        }

        return BuiltInBuilder::buildStrict($name, $value);
    }
}
