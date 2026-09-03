<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Builders;

use BackedEnum;
use Fabiomez\ObjectConstructor\ConstructException;
use UnitEnum;

abstract class EnumBuilder
{
    final public static function build(string $type, mixed $value): UnitEnum|BackedEnum
    {
        if (is_a($type, BackedEnum::class, true)) {
            return $type::from($value);
        }

        if (is_a($type, UnitEnum::class, true)) {
            return array_filter(
                $type::cases(),
                static fn (UnitEnum $case) => $case->name == $value
            )[0] ?? throw new ConstructException('', "Invalid enum value: $value");
        }
    }
}
