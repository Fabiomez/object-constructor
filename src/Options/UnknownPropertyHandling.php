<?php

declare(strict_types=1);

namespace Fabiomez\ObjectConstructor\Options;

enum UnknownPropertyHandling
{
    case IGNORE;
    case FAIL;
}
