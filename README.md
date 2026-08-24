# Object Constructor

Construct typed PHP objects from structured arrays and scalar values using constructor type declarations, with nested objects, backed enums, collections and custom factories.

## Features

- Reflection metadata cache to avoid repeated constructor inspection.
- Nested object construction.
- Backed enum construction.
- `DateTime` and `DateTimeImmutable` support.
- Union and intersection type handling.
- `#[Collection]` and `#[Factoryable]` attributes.
- Strict and coercive scalar modes.
- Configurable unknown-property handling.
- Extensible custom `ValueResolver` pipeline.
- Detailed nested construction exceptions with previous exceptions preserved.

## Usage

```php
use Fabiomez\ObjectConstructor\Constructor;

$constructor = new Constructor();

$user = $constructor->create(User::class, [
    'id' => '123',
    'name' => ['first' => 'Fabio', 'last' => 'Mezini'],
]);
```

`ConstructionMode::COERCE` is the default and preserves the library's original convenience-oriented behavior. Use `ConstructionMode::STRICT` when external input must already match declared scalar types.

```php
use Fabiomez\ObjectConstructor\Options\ConstructionMode;
use Fabiomez\ObjectConstructor\Options\ConstructionOptions;

$user = $constructor->create(
    User::class,
    $input,
    new ConstructionOptions(ConstructionMode::STRICT),
);
```

Unknown properties are ignored by default. To make malformed input fail fast:

```php
use Fabiomez\ObjectConstructor\Options\UnknownPropertyHandling;

$options = new ConstructionOptions(
    unknownProperties: UnknownPropertyHandling::FAIL,
);
```

## Custom resolvers

Implement `ValueResolver` when an application-specific type needs a custom conversion strategy. Resolvers are evaluated before the built-in type handling.

```php
final class UuidResolver implements ValueResolver
{
    public function supports(ParameterMetadata $parameter, mixed $value): bool
    {
        return $parameter->type?->getName() === Uuid::class;
    }

    public function resolve(Constructor $constructor, ParameterMetadata $parameter, mixed $value): Uuid
    {
        return Uuid::fromString($value);
    }
}

$constructor = new Constructor(resolvers: [new UuidResolver()]);
```

## Requirements

PHP 8.1 or newer.

## Development

Run the test suite with PHPUnit. The project CI exercises supported PHP versions and static analysis is intentionally kept lightweight so the library remains dependency-free at runtime.
