<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Fabiomez\ObjectConstructor\Builders;

final class BenchmarkValue
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}

$constructor = new Constructor();
$input = ['id' => '123', 'name' => 'benchmark'];
$iterations = (int) ($argv[1] ?? 10000);

$start = hrtime(true);
for ($i = 0; $i < $iterations; ++$i) {
    $constructor->construct(BenchmarkValue::class, $input);
}
$elapsed = (hrtime(true) - $start) / 1e6;

printf("%d constructions: %.3f ms (%.3f µs/op)%s", $iterations, $elapsed, ($elapsed * 1000) / $iterations, PHP_EOL);
