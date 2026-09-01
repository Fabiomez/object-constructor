<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Options;

enum ConstructionMode
{
    case COERCE;
    case STRICT;
}
